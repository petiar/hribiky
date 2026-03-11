<?php

namespace App\Repository;

use App\Entity\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogPost::class);
    }

    /**
     * @return BlogPost[]
     */
    public function findScheduledForPublishing(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.published = false')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return BlogPost[]
     */
    public function findRelated(BlogPost $post, int $limit = 3): array
    {
        if (empty($post->getTags())) {
            return $this->createQueryBuilder('b')
                ->andWhere('b.published = true')
                ->andWhere('b.id != :id')
                ->setParameter('id', $post->getId())
                ->orderBy('b.publishedAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }

        $qb = $this->createQueryBuilder('b');
        $orX = $qb->expr()->orX();
        foreach ($post->getTags() as $i => $tag) {
            $orX->add($qb->expr()->like('b.tags', ':tag' . $i));
            $qb->setParameter('tag' . $i, '%' . $tag . '%');
        }

        return $qb
            ->andWhere('b.published = true')
            ->andWhere('b.id != :id')
            ->setParameter('id', $post->getId())
            ->andWhere($orX)
            ->orderBy('b.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return BlogPost[]
     */
    public function findAllPublished(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.published = :pub')
            ->setParameter('pub', true)
            ->orderBy('b.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}