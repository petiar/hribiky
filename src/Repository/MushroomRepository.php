<?php

namespace App\Repository;

use App\Entity\Mushroom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MushroomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mushroom::class);
    }

    public function findNearby(float $lat, float $lng, float $radius = 100): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
        SELECT h.*,
            (6371000 * acos(
                cos(radians(:lat)) * cos(radians(h.latitude)) *
                cos(radians(h.longitude) - radians(:lng)) +
                sin(radians(:lat)) * sin(radians(h.latitude))
            )) AS distance
        FROM mushroom h
        WHERE published = 1
        HAVING distance < :radius
        ORDER BY distance ASC
    ";

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius,
        ]);

        return $result->fetchAllAssociative();
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
