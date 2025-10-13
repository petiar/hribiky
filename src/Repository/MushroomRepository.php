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
}
