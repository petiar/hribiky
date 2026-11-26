<?php

namespace App\Service;

use App\Entity\Mushroom;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Enum\BadgeType;
use App\Repository\MushroomRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

class BadgeCalculatorService
{
    private const VYTRVALEC_WEEKS = 8;
    private const ROZPRAVAC_MIN_DESCRIPTIONS = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private MushroomRepository $mushroomRepository,
        private UserBadgeRepository $userBadgeRepository,
        private UserRepository $userRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Musí bežať AŽ PO app:count-mushrooms — víťazi sa dohľadávajú cez User::email,
     * a pre nové emaily ten riadok vytvára práve app:count-mushrooms.
     */
    public function calculateAll(): int
    {
        $affected = 0;

        $this->entityManager->wrapInTransaction(function () use (&$affected) {
            if ($this->calculateAltitudeExtreme(BadgeType::Vyskar, 'DESC')) {
                $affected++;
            }
            if ($this->calculateAltitudeExtreme(BadgeType::Krtko, 'ASC')) {
                $affected++;
            }
            if ($this->calculateCestovatel()) {
                $affected++;
            }
            if ($this->calculateRozpravac()) {
                $affected++;
            }
            if ($this->calculateFotograf()) {
                $affected++;
            }
            if ($this->calculateKronikar()) {
                $affected++;
            }
            $affected += $this->calculateSezonnyZberatel();
            $affected += $this->calculateVytrvalec();
        });

        return $affected;
    }

    private function calculateAltitudeExtreme(BadgeType $type, string $direction): bool
    {
        $baseQuery = fn() => $this->mushroomRepository->createQueryBuilder('m')
            ->andWhere('m.published = true')
            ->andWhere('m.altitude IS NOT NULL')
            ->andWhere('m.email IS NOT NULL')
            ->andWhere("m.email <> ''");

        $top = $baseQuery()
            ->orderBy('m.altitude', $direction)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($top === null) {
            return false;
        }

        // Všetky hríbiky s rovnakou extrémnou výškou; skôr pridaný má prednosť.
        /** @var Mushroom[] $tied */
        $tied = $baseQuery()
            ->andWhere('m.altitude = :altitude')
            ->setParameter('altitude', $top->getAltitude())
            ->orderBy('m.createdAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        $candidates = array_map(fn(Mushroom $m) => [
            'email' => $m->getEmail(),
            'value' => (float) $m->getAltitude(),
            'evidence' => $m,
        ], $tied);

        $winner = $this->pickTieWinner($type, $candidates);

        return $this->assignTrophy($type, $this->findUserByEmail($winner['email']), $winner['value'], $winner['evidence']);
    }

    /**
     * Rozprávač = najvyššie priemerné AI skóre popisov za posledný rok,
     * len pre autorov s aspoň ROZPRAVAC_MIN_DESCRIPTIONS ohodnotenými popismi.
     */
    private function calculateRozpravac(): bool
    {
        $since = $this->clock->now()->modify('-1 year')->format('Y-m-d H:i:s');

        $rows = $this->connection->fetchAllAssociative(
            "SELECT email, AVG(description_score) AS avg_score, COUNT(*) AS cnt, MAX(created_at) AS last_at
             FROM mushroom
             WHERE published = 1 AND description_score IS NOT NULL
               AND email IS NOT NULL AND email <> ''
               AND created_at >= :since
             GROUP BY email
             HAVING cnt >= :minCount
             ORDER BY avg_score DESC, cnt DESC, last_at ASC",
            ['since' => $since, 'minCount' => self::ROZPRAVAC_MIN_DESCRIPTIONS],
            // Bez explicitného typu by sa minCount poslal ako string a SQLite by cnt >= '3' vyhodnotil vždy ako false.
            ['minCount' => ParameterType::INTEGER]
        );

        // Nikto za posledný rok nespĺňa minimum → trofej nemá mať nikto.
        if (empty($rows)) {
            return $this->revokeTrophy(BadgeType::Rozpravac);
        }

        $topAvg = (float) $rows[0]['avg_score'];
        $topCount = (int) $rows[0]['cnt'];
        $candidates = [];
        foreach ($rows as $row) {
            if (!$this->valuesEqual((float) $row['avg_score'], $topAvg) || (int) $row['cnt'] !== $topCount) {
                break;
            }
            $candidates[] = ['email' => (string) $row['email'], 'value' => round($topAvg, 2), 'evidence' => null];
        }

        $winner = $this->pickTieWinner(BadgeType::Rozpravac, $candidates);

        // Dôkaz = najlepšie ohodnotený popis víťaza v sledovanom období.
        $evidence = $this->mushroomRepository->createQueryBuilder('m')
            ->andWhere('m.published = true')
            ->andWhere('m.email = :email')
            ->andWhere('m.descriptionScore IS NOT NULL')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('email', $winner['email'])
            ->setParameter('since', new \DateTimeImmutable($since))
            ->orderBy('m.descriptionScore', 'DESC')
            ->addOrderBy('m.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $this->assignTrophy(BadgeType::Rozpravac, $this->findUserByEmail($winner['email']), $winner['value'], $evidence);
    }

    private function calculateFotograf(): bool
    {
        // Pri rovnakom počte má prednosť ten, kto ho dosiahol skôr (starší posledný príspevok).
        $rows = $this->connection->fetchAllAssociative(
            "SELECT m.email, COUNT(p.id) AS cnt, MAX(p.id) AS last_id
             FROM mushroom m
             JOIN photo p ON p.mushroom_id = m.id
             WHERE m.email IS NOT NULL AND m.email <> '' AND m.published = 1
             GROUP BY m.email
             ORDER BY cnt DESC, last_id ASC"
        );

        return $this->assignCountTrophy(BadgeType::Fotograf, $rows);
    }

    private function calculateKronikar(): bool
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT email, COUNT(*) AS cnt, MAX(created_at) AS last_at
             FROM mushroom_comment
             WHERE email IS NOT NULL AND email <> '' AND published = 1
             GROUP BY email
             ORDER BY cnt DESC, last_at ASC"
        );

        return $this->assignCountTrophy(BadgeType::Kronikar, $rows);
    }

    /**
     * @param array<int, array{email: string, cnt: int|string}> $rows zoradené podľa cnt DESC + tiebreak
     */
    private function assignCountTrophy(BadgeType $type, array $rows): bool
    {
        if (empty($rows)) {
            return false;
        }

        $topCount = (int) $rows[0]['cnt'];
        $candidates = [];
        foreach ($rows as $row) {
            if ((int) $row['cnt'] !== $topCount) {
                break;
            }
            $candidates[] = ['email' => (string) $row['email'], 'value' => (float) $topCount, 'evidence' => null];
        }

        $winner = $this->pickTieWinner($type, $candidates);

        return $this->assignTrophy($type, $this->findUserByEmail($winner['email']), $winner['value'], null);
    }

    private function calculateCestovatel(): bool
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT email, latitude, longitude FROM mushroom WHERE published = 1 AND email IS NOT NULL AND email <> ''"
        );

        $pointsByEmail = [];
        foreach ($rows as $row) {
            $pointsByEmail[$row['email']][] = [(float) $row['latitude'], (float) $row['longitude']];
        }

        $bestEmail = null;
        $bestDistance = 0.0;

        foreach ($pointsByEmail as $email => $points) {
            $count = count($points);
            if ($count < 2) {
                continue;
            }

            $maxDistance = 0.0;
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $distance = $this->haversineDistance($points[$i][0], $points[$i][1], $points[$j][0], $points[$j][1]);
                    if ($distance > $maxDistance) {
                        $maxDistance = $distance;
                    }
                }
            }

            if ($maxDistance > $bestDistance) {
                $bestDistance = $maxDistance;
                $bestEmail = $email;
            }
        }

        if ($bestEmail === null) {
            return false;
        }

        $user = $this->findUserByEmail($bestEmail);
        if ($user === null) {
            return false;
        }

        return $this->assignTrophy(BadgeType::Cestovatel, $user, $bestDistance, null);
    }

    private function calculateSezonnyZberatel(): int
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT email, created_at FROM mushroom WHERE published = 1 AND email IS NOT NULL AND email <> ''"
        );

        $seasonsByEmail = [];
        foreach ($rows as $row) {
            $month = (int) (new \DateTimeImmutable($row['created_at']))->format('n');
            $seasonsByEmail[$row['email']][$this->seasonForMonth($month)] = true;
        }

        $affected = 0;
        foreach ($seasonsByEmail as $email => $seasons) {
            if (count($seasons) < 4) {
                continue;
            }

            $user = $this->findUserByEmail($email);
            if ($user === null) {
                continue;
            }

            if ($this->grantAchievement(BadgeType::SezonnyZberatel, $user, null, null)) {
                $affected++;
            }
        }

        return $affected;
    }

    private function calculateVytrvalec(): int
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT email, created_at FROM mushroom WHERE published = 1 AND email IS NOT NULL AND email <> ''"
        );

        $weeksByEmail = [];
        foreach ($rows as $row) {
            $timestamp = (new \DateTimeImmutable($row['created_at']))->getTimestamp();
            $week = intdiv($timestamp, 7 * 86400);
            $weeksByEmail[$row['email']][$week] = true;
        }

        $affected = 0;
        foreach ($weeksByEmail as $email => $weeks) {
            $weekNumbers = array_keys($weeks);
            sort($weekNumbers);

            $longestStreak = 1;
            $currentStreak = 1;
            for ($i = 1, $count = count($weekNumbers); $i < $count; $i++) {
                if ($weekNumbers[$i] === $weekNumbers[$i - 1] + 1) {
                    $currentStreak++;
                    $longestStreak = max($longestStreak, $currentStreak);
                } else {
                    $currentStreak = 1;
                }
            }

            if ($longestStreak < self::VYTRVALEC_WEEKS) {
                continue;
            }

            $user = $this->findUserByEmail($email);
            if ($user === null) {
                continue;
            }

            if ($this->grantAchievement(BadgeType::Vytrvalec, $user, (float) $longestStreak, null)) {
                $affected++;
            }
        }

        return $affected;
    }

    private function seasonForMonth(int $month): string
    {
        return match (true) {
            in_array($month, [12, 1, 2], true) => 'winter',
            in_array($month, [3, 4, 5], true) => 'spring',
            in_array($month, [6, 7, 8], true) => 'summer',
            default => 'autumn',
        };
    }

    /**
     * Pri remíze si trofej ponechá súčasný držiteľ; inak vyhrá prvý kandidát
     * (kandidáti musia byť deterministicky zoradení).
     *
     * @param non-empty-array<int, array{email: string, value: ?float, evidence: ?Mushroom}> $candidates
     * @return array{email: string, value: ?float, evidence: ?Mushroom}
     */
    private function pickTieWinner(BadgeType $type, array $candidates): array
    {
        $existing = $this->userBadgeRepository->findByType($type);
        if ($existing !== null && count($candidates) > 1) {
            $holderEmail = mb_strtolower(trim((string) $existing->getUser()->getEmail()));
            foreach ($candidates as $candidate) {
                if (mb_strtolower(trim($candidate['email'])) === $holderEmail) {
                    return $candidate;
                }
            }
        }

        return $candidates[0];
    }

    /**
     * Floaty po round-tripe cez DB (DOUBLE stĺpec) sa nemusia zhodovať bit-presne s === —
     * porovnávame preto s malou toleranciou, aby sa nezaznamenávala zmena pri identickej hodnote.
     */
    private function valuesEqual(?float $a, ?float $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return abs($a - $b) < 0.0001;
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function findUserByEmail(?string $email): ?User
    {
        if ($email === null || $email === '') {
            return null;
        }

        return $this->userRepository->findOneBy(['email' => $email]);
    }

    /**
     * Trofej = vždy len 1 aktuálny držiteľ. Ak sa zmenil, starý riadok sa zmaže a vloží sa nový.
     */
    private function assignTrophy(BadgeType $type, ?User $winner, ?float $value, ?Mushroom $evidence): bool
    {
        if ($winner === null) {
            return false;
        }

        $existing = $this->userBadgeRepository->findByType($type);

        if ($existing !== null && $existing->getUser() === $winner) {
            if ($this->valuesEqual($existing->getValue(), $value)) {
                return false;
            }
            $existing->setValue($value);
            $existing->setEvidenceMushroom($evidence);
            $existing->setAwardedAt($this->clock->now());

            return true;
        }

        if ($existing !== null) {
            $this->entityManager->remove($existing);
        }

        $badge = new UserBadge();
        $badge->setUser($winner);
        $badge->setType($type);
        $badge->setValue($value);
        $badge->setEvidenceMushroom($evidence);
        $badge->setAwardedAt($this->clock->now());
        $this->entityManager->persist($badge);

        return true;
    }

    /**
     * Zmaže trofej, ak ju niekto drží. Vráti true, ak sa niečo zmazalo.
     */
    private function revokeTrophy(BadgeType $type): bool
    {
        $existing = $this->userBadgeRepository->findByType($type);
        if ($existing === null) {
            return false;
        }

        $this->entityManager->remove($existing);

        return true;
    }

    /**
     * Úspech = insert-if-absent, raz získaný sa nestráca ani keď ho niekto iný prekoná.
     */
    private function grantAchievement(BadgeType $type, User $user, ?float $value, ?Mushroom $evidence): bool
    {
        if ($this->userBadgeRepository->findByUserAndType($user, $type) !== null) {
            return false;
        }

        $badge = new UserBadge();
        $badge->setUser($user);
        $badge->setType($type);
        $badge->setValue($value);
        $badge->setEvidenceMushroom($evidence);
        $badge->setAwardedAt($this->clock->now());
        $this->entityManager->persist($badge);

        return true;
    }
}
