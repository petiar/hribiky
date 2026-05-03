<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return User[]
     */
    public function findTopContributors(int $limit = 50): array
    {
        return $this->createQueryBuilder('u')
            ->addSelect('(u.mushroomCount * 2 + COALESCE(u.commentCount, 0)) AS HIDDEN score')
            ->andWhere('(u.mushroomCount > 0 OR u.commentCount > 0)')
            ->andWhere('u.name IS NOT NULL AND u.name <> \'\'')
            ->orderBy('score', 'DESC')
            ->addOrderBy('u.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
