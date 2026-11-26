<?php

namespace App\Repository;

use App\Entity\MushroomComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class MushroomCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $manager)
    {
        parent::__construct($manager, MushroomComment::class);
    }

    /**
     * Publikované komentáre pre GET /api/mushrooms_comments, od najnovšieho, aj s fotkami.
     *
     * Fotky sa načítajú v tom istom dotaze (fetch join) — inak by sa pre každý komentár
     * spravil samostatný dotaz.
     *
     * @param int|null $limit null = všetky
     * @return MushroomComment[]
     */
    public function findPublishedForApi(int $sinceId = 0, int $mushroomId = 0, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->addSelect('p')
            ->leftJoin('c.photos', 'p')
            ->andWhere('c.published = true')
            ->orderBy('c.id', 'DESC')
            ->addOrderBy('p.id', 'ASC');

        if ($sinceId > 0) {
            $qb->andWhere('c.id > :since')->setParameter('since', $sinceId);
        }
        if ($mushroomId > 0) {
            $qb->andWhere('c.mushroom = :mushroomId')->setParameter('mushroomId', $mushroomId);
        }

        if ($limit === null) {
            return $qb->getQuery()->getResult();
        }

        // S fetch joinom by LIMIT obmedzil riadky (komentár × fotka), nie komentáre — Paginator to rieši.
        $qb->setMaxResults($limit);

        return iterator_to_array(new Paginator($qb->getQuery(), fetchJoinCollection: true), false);
    }
}
