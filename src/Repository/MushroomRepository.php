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
