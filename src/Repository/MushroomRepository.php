<?php

namespace App\Repository;

use App\Entity\Mushroom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class MushroomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mushroom::class);
    }

    /**
     * Publikované hríbiky pre GET /api/mushrooms, od najnovšieho, aj s fotkami.
     *
     * Fotky a edit link sa načítajú v tom istom dotaze (fetch join) — inak by sa pre každý
     * hríbik spravil samostatný dotaz (pri stovkách hríbikov stovky dotazov).
     *
     * @param int|null $limit null = všetky
     * @return Mushroom[]
     */
    public function findPublishedForApi(int $sinceId = 0, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->addSelect('p', 'el')
            ->leftJoin('m.photos', 'p')
            // editLink je inverzná strana OneToOne — Doctrine ju nevie načítať lenivo a inak
            // by ju dotiahol samostatným dotazom pre každý hríbik.
            ->leftJoin('m.editLink', 'el')
            ->andWhere('m.published = true')
            ->orderBy('m.id', 'DESC')
            ->addOrderBy('p.id', 'ASC');

        if ($sinceId > 0) {
            $qb->andWhere('m.id > :since')->setParameter('since', $sinceId);
        }

        if ($limit === null) {
            return $qb->getQuery()->getResult();
        }

        // S fetch joinom by LIMIT obmedzil riadky (hríbik × fotka), nie hríbiky — Paginator to rieši.
        $qb->setMaxResults($limit);

        return iterator_to_array(new Paginator($qb->getQuery(), fetchJoinCollection: true), false);
    }

    /**
     * Výsledok ide priamo do verejných API odpovedí (/api/nearby bez API kľúča, /api/mushrooms/nearby).
     * Preto vyberáme LEN verejné stĺpce — nikdy nie h.* (unikal by email, approval_token a pod.).
     * Nový stĺpec pridávaj sem len vtedy, ak smie byť verejný.
     *
     * @return array<int, array{id: int, title: string, name: string, description: ?string,
     *     latitude: float, longitude: float, altitude: ?int, created_at: string, distance: float}>
     */
    public function findNearby(float $lat, float $lng, float $radius = 100): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
        SELECT * FROM (
            SELECT h.id, h.title, h.name, h.description, h.latitude, h.longitude, h.altitude, h.created_at,
                (6371000 * acos(
                    cos(radians(:lat)) * cos(radians(h.latitude)) *
                    cos(radians(h.longitude) - radians(:lng)) +
                    sin(radians(:lat)) * sin(radians(h.latitude))
                )) AS distance
            FROM mushroom h
            WHERE published = 1
        ) AS sub
        WHERE distance < :radius
        ORDER BY distance ASC
    ";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('lat', $lat);
        $stmt->bindValue('lng', $lng);
        $stmt->bindValue('radius', (int) $radius, ParameterType::INTEGER);

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Schválené hríbiky v okolí daného (bez neho), od najbližšieho — na detail hríbika.
     *
     * @return list<array{mushroom: Mushroom, distance: int}> distance v metroch
     */
    public function findNeighbours(Mushroom $mushroom, int $limit = 6, int $radiusMeters = 25000): array
    {
        // Kandidáti v obdĺžniku okolo hríbika (lacné, bez SQL funkcií — funguje aj v SQLite testoch),
        // presnú vzdialenosť a poradie dopočítame v PHP.
        $lat = $mushroom->getLatitude();
        $lng = $mushroom->getLongitude();
        $dLat = $radiusMeters / 111_000;
        $dLng = $dLat / max(0.1, cos(deg2rad($lat)));

        $candidates = $this->createQueryBuilder('m')
            ->leftJoin('m.photos', 'p')->addSelect('p')
            ->where('m.published = true')
            ->andWhere('m.id <> :id')
            ->andWhere('m.latitude BETWEEN :minLat AND :maxLat')
            ->andWhere('m.longitude BETWEEN :minLng AND :maxLng')
            ->setParameter('id', $mushroom->getId())
            ->setParameter('minLat', $lat - $dLat)->setParameter('maxLat', $lat + $dLat)
            ->setParameter('minLng', $lng - $dLng)->setParameter('maxLng', $lng + $dLng)
            ->getQuery()->getResult();

        $result = [];
        foreach ($candidates as $candidate) {
            $distance = self::distanceMeters($lat, $lng, $candidate->getLatitude(), $candidate->getLongitude());
            if ($distance <= $radiusMeters) {
                $result[] = ['mushroom' => $candidate, 'distance' => (int) round($distance)];
            }
        }
        usort($result, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        return array_slice($result, 0, $limit);
    }

    /** Vzdialenosť dvoch bodov po povrchu Zeme v metroch (haversine). */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * 6371000 * asin(min(1, sqrt($h)));
    }

    public function findOneWithoutBlogPost(): ?Mushroom
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT id FROM mushroom WHERE published = 1 AND blog_post_generated = 0 AND description IS NOT NULL ORDER BY RAND() LIMIT 1';
        $id = $conn->fetchOne($sql);

        if (!$id) {
            return null;
        }

        return $this->find($id);
    }

    public function countByEmail(string $email): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Mushroom[]
     */
    /**
     * Pre sitemap: komentáre (lastmod = posledný publikovaný komentár) a edit link v tom istom dotaze —
     * predtým 2 dotazy navyše pre každý hríbik.
     *
     * @return Mushroom[]
     */
    public function findAllPublished(): array {
        return $this->createQueryBuilder('m')
            ->addSelect('u', 'el')
            ->leftJoin('m.updates', 'u')
            ->leftJoin('m.editLink', 'el')
            ->andWhere('m.published = :pub')->setParameter('pub', 1)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()->getResult();
    }
}
