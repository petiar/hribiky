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
        $tagSlugs = $post->getTags()->map(fn($t) => $t->getSlug())->toArray();

        if (empty($tagSlugs)) {
            return $this->createQueryBuilder('b')
                ->andWhere('b.published = true')
                ->andWhere('b.id != :id')
                ->setParameter('id', $post->getId())
                ->orderBy('b.publishedAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }

        return $this->createQueryBuilder('b')
            ->join('b.tags', 't')
            ->andWhere('b.published = true')
            ->andWhere('b.id != :id')
            ->andWhere('t.slug IN (:slugs)')
            ->setParameter('id', $post->getId())
            ->setParameter('slugs', $tagSlugs)
            ->orderBy('b.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return BlogPost[]
     */
    public function findByTag(string $tagSlug): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.tags', 't')
            ->andWhere('b.published = true')
            ->andWhere('t.slug = :slug')
            ->setParameter('slug', $tagSlug)
            ->orderBy('b.publishedAt', 'DESC')
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