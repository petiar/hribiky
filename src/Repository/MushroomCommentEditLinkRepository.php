<?php

namespace App\Repository;

use App\Entity\MushroomCommentEditLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class MushroomCommentEditLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, MushroomCommentEditLink::class);
    }

    public function deleteOldUsedLinks(): int
    {
        $limitDate = (new \DateTimeImmutable())->sub(new \DateInterval('P7D'));

        return $this->entityManager->createQueryBuilder()
            ->delete(MushroomCommentEditLink::class, 'l')
            ->where('l.usedAt IS NOT NULL')
            ->andWhere('l.createdAt < :limitDate')
            ->setParameter('limitDate', $limitDate)
            ->getQuery()
            ->execute();
    }
}