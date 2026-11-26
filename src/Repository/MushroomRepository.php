<?php

namespace App\Repository;

use App\Entity\Mushroom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

class MushroomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mushroom::class);
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
    public function findAllPublished(): array {
        return $this->createQueryBuilder('m')
            ->andWhere('m.published = :pub')->setParameter('pub', 1)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()->getResult();
    }
}
