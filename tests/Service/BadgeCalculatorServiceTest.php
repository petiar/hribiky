<?php

namespace App\Tests\Service;

use App\Command\CalculateBadgesCommand;
use App\Entity\Mushroom;
use App\Entity\Photo;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Enum\BadgeType;
use App\Repository\MushroomRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserRepository;
use App\Service\BadgeCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class BadgeCalculatorServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BadgeCalculatorService $calculator;
    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $doctrine = static::getContainer()->get('doctrine');
        $this->em = $doctrine->getManager();

        // Pevný čas — testy nezávisia od dňa, kedy bežia.
        $this->clock = new MockClock('2026-06-15 12:00:00');
        $this->calculator = new BadgeCalculatorService(
            $this->em,
            $this->em->getConnection(),
            new MushroomRepository($doctrine),
            new UserBadgeRepository($doctrine),
            new UserRepository($doctrine),
            $this->clock,
        );

        foreach ([UserBadge::class, Photo::class, Mushroom::class, User::class] as $class) {
            $this->em->createQuery('DELETE FROM ' . $class . ' e')->execute();
        }
    }

    // --- Remízy pri trofejach ---

    public function testAltitudeTieKeepsCurrentHolder(): void
    {
        $this->createUser('a@test.sk');
        $this->createUser('b@test.sk');
        $this->createMushroom('a@test.sk', altitude: 1500, createdAt: '-1 month');

        $this->calculator->calculateAll();
        $this->assertBadgeHolder(BadgeType::Vyskar, 'a@test.sk');

        // B dorovná výšku a jeho hríbik je dokonca starší — bez ochrany držiteľa by vyhral.
        $this->createMushroom('b@test.sk', altitude: 1500, createdAt: '-2 years');

        $this->calculator->calculateAll();
        $this->assertBadgeHolder(BadgeType::Vyskar, 'a@test.sk');
    }

    public function testAltitudeTieWithoutHolderPrefersEarlierMushroom(): void
    {
        $this->createUser('a@test.sk');
        $this->createUser('b@test.sk');
        $this->createMushroom('a@test.sk', altitude: 1500, createdAt: '-1 month');
        $this->createMushroom('b@test.sk', altitude: 1500, createdAt: '-2 years');

        $this->calculator->calculateAll();

        $this->assertBadgeHolder(BadgeType::Vyskar, 'b@test.sk');
    }

    public function testAltitudeTrophyMovesWhenBeaten(): void
    {
        $this->createUser('a@test.sk');
        $this->createUser('b@test.sk');
        $this->createMushroom('a@test.sk', altitude: 1500);

        $this->calculator->calculateAll();
        $this->createMushroom('b@test.sk', altitude: 1600);
        $this->calculator->calculateAll();

        $this->assertBadgeHolder(BadgeType::Vyskar, 'b@test.sk');
        $this->assertBadgeHolder(BadgeType::Krtko, 'a@test.sk');
    }

    public function testFotografTieKeepsCurrentHolderUntilBeaten(): void
    {
        $this->createUser('a@test.sk');
        $this->createUser('b@test.sk');
        $mushroomA = $this->createMushroom('a@test.sk');
        $mushroomB = $this->createMushroom('b@test.sk');
        $this->createPhotos($mushroomA, 2);

        $this->calculator->calculateAll();
        $this->assertBadgeHolder(BadgeType::Fotograf, 'a@test.sk');

        // Remíza 2:2 — trofej zostáva A.
        $this->createPhotos($mushroomB, 2);
        $this->calculator->calculateAll();
        $this->assertBadgeHolder(BadgeType::Fotograf, 'a@test.sk');

        // B predbehne 3:2 — trofej prechádza na B.
        $this->createPhotos($mushroomB, 1);
        $this->calculator->calculateAll();
        $this->assertBadgeHolder(BadgeType::Fotograf, 'b@test.sk', 3.0);
    }

    // --- Rozprávač ---

    public function testRozpravacUsesAverageAndRequiresMinimumDescriptions(): void
    {
        $this->createUser('a@test.sk');
        $this->createUser('b@test.sk');
        foreach ([7, 8, 9] as $score) {
            $this->createMushroom('a@test.sk', score: $score);
        }
        // B má vyšší priemer, ale len 2 popisy — nezapočíta sa.
        foreach ([10, 10] as $score) {
            $this->createMushroom('b@test.sk', score: $score);
        }

        $this->calculator->calculateAll();

        $this->assertBadgeHolder(BadgeType::Rozpravac, 'a@test.sk', 8.0);
    }

    public function testRozpravacEvidenceIsBestDescriptionOfWinner(): void
    {
        $this->createUser('a@test.sk');
        $this->createMushroom('a@test.sk', score: 5);
        $best = $this->createMushroom('a@test.sk', score: 9);
        $this->createMushroom('a@test.sk', score: 6);

        $this->calculator->calculateAll();

        $badge = $this->findBadge(BadgeType::Rozpravac);
        $this->assertNotNull($badge);
        $this->assertSame($best->getId(), $badge->getEvidenceMushroom()?->getId());
    }

    public function testRozpravacIgnoresDescriptionsOlderThanOneYear(): void
    {
        $this->createUser('a@test.sk');
        $this->createUser('b@test.sk');
        foreach ([5, 5, 5] as $score) {
            $this->createMushroom('a@test.sk', createdAt: '-1 month', score: $score);
        }
        foreach ([10, 10, 10] as $score) {
            $this->createMushroom('b@test.sk', createdAt: '-2 years', score: $score);
        }

        $this->calculator->calculateAll();

        $this->assertBadgeHolder(BadgeType::Rozpravac, 'a@test.sk', 5.0);
    }

    public function testRozpravacIsRevokedWhenNobodyQualifies(): void
    {
        $this->createUser('a@test.sk');
        foreach ([6, 7, 8] as $score) {
            $this->createMushroom('a@test.sk', score: $score);
        }

        $this->calculator->calculateAll();
        $this->assertBadgeHolder(BadgeType::Rozpravac, 'a@test.sk');

        // Uplynú dva roky → popisy vypadnú z ročného okna a trofej nemá mať nikto.
        $this->clock->modify('+2 years');

        $this->calculator->calculateAll();
        $this->assertNull($this->findBadge(BadgeType::Rozpravac));
    }

    // --- Command ---

    public function testCommandReturnsSuccessEvenWhenBadgesChanged(): void
    {
        $this->createUser('a@test.sk');
        $this->createMushroom('a@test.sk', altitude: 1500);

        $tester = new CommandTester(new CalculateBadgesCommand($this->calculator));
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Zmenených odznakov:', $tester->getDisplay());
        $this->assertNotNull($this->findBadge(BadgeType::Vyskar));
    }

    public function testAwardedAtComesFromClock(): void
    {
        $this->createUser('a@test.sk');
        $this->createMushroom('a@test.sk', altitude: 1500);

        $this->calculator->calculateAll();

        $this->assertEquals(
            new \DateTimeImmutable('2026-06-15 12:00:00'),
            $this->findBadge(BadgeType::Vyskar)->getAwardedAt()
        );
    }

    // --- Pomocné metódy ---

    private function createUser(string $email): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPassword('disabled');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createMushroom(
        string $email,
        ?int $altitude = null,
        string $createdAt = '-1 week',
        ?int $score = null,
    ): Mushroom {
        $mushroom = (new Mushroom())
            ->setTitle('Hríbik ' . uniqid())
            ->setName('Tester')
            ->setEmail($email)
            ->setLatitude(48.1486)
            ->setLongitude(17.1077)
            ->setAltitude($altitude)
            ->setPublished(true)
            ->setCreatedAt(\DateTime::createFromImmutable($this->clock->now()->modify($createdAt)));

        if ($score !== null) {
            $mushroom
                ->setDescription('Popis hríbika')
                ->setDescriptionScore($score)
                ->setDescriptionScoredAt($this->clock->now());
        }

        $this->em->persist($mushroom);
        $this->em->flush();

        return $mushroom;
    }

    private function createPhotos(Mushroom $mushroom, int $count): void
    {
        // findBadge() volá em->clear(), takže pôvodný objekt už môže byť odpojený.
        $mushroom = $this->em->getReference(Mushroom::class, $mushroom->getId());
        for ($i = 0; $i < $count; $i++) {
            $photo = (new Photo())
                ->setPath(uniqid() . '.jpg')
                ->setOwner(Mushroom::class)
                ->setMushroom($mushroom);
            $this->em->persist($photo);
        }
        $this->em->flush();
    }

    private function findBadge(BadgeType $type): ?UserBadge
    {
        $this->em->clear();

        return $this->em->getRepository(UserBadge::class)->findOneBy(['type' => $type]);
    }

    private function assertBadgeHolder(BadgeType $type, string $email, ?float $value = null): void
    {
        $badge = $this->findBadge($type);

        $this->assertNotNull($badge, sprintf('Odznak %s nemá nikto.', $type->value));
        $this->assertSame($email, $badge->getUser()->getEmail());
        if ($value !== null) {
            $this->assertEqualsWithDelta($value, $badge->getValue(), 0.0001);
        }
    }
}
