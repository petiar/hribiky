<?php

// src/Repository/MushroomEditLinkRepository.php

namespace App\Repository;

use App\Entity\MushroomEditLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class MushroomEditLinkRepository extends ServiceEntityRepository
{

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    public function __construct(
        ManagerRegistry $registry,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($registry, MushroomEditLink::class);
        $this->entityManager = $entityManager;
    }

    /**
     * Zmaže všetky linky, kde usedAt IS NOT NULL
     * a createdAt je starší ako 7 dní.
     *
     * @return int počet zmazaných riadkov
     */
    public function deleteOldUsedLinks(): int
    {
        $limitDate = (new \DateTimeImmutable())->sub(new \DateInterval('P7D'));

        $qb = $this->entityManager->createQueryBuilder();

        return $qb->delete(MushroomEditLink::class, 'm')
            ->where('m.usedAt IS NOT NULL')
            ->andWhere('m.createdAt < :limitDate')
            ->setParameter('limitDate', $limitDate)
            ->getQuery()
            ->execute();
    }
}
