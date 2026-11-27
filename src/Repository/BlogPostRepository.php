<?php

namespace App\Repository;

use App\Entity\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
     * Jedna stránka publikovaných článkov s daným tagom, od najnovšieho.
     *
     * @return Paginator<BlogPost>
     */
    public function findByTagPage(string $tagSlug, int $page, int $perPage): Paginator
    {
        $query = $this->createQueryBuilder('b')
            ->join('b.tags', 't')
            ->andWhere('b.published = true')
            ->andWhere('t.slug = :slug')
            ->setParameter('slug', $tagSlug)
            ->orderBy('b.publishedAt', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        // Join slúži len na filter (tagy sa nenačítavajú), preto fetchJoinCollection netreba.
        return new Paginator($query, fetchJoinCollection: false);
    }

    /**
     * Jedna stránka publikovaných článkov, od najnovšieho.
     * count($paginator) vráti celkový počet publikovaných článkov.
     *
     * @return Paginator<BlogPost>
     */
    public function findPublishedPage(int $page, int $perPage): Paginator
    {
        $query = $this->createQueryBuilder('b')
            ->andWhere('b.published = :pub')
            ->setParameter('pub', true)
            ->orderBy('b.publishedAt', 'DESC')
            ->addOrderBy('b.id', 'DESC') // stabilné poradie pri rovnakom dátume
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        return new Paginator($query, fetchJoinCollection: false);
    }

    /**
     * Fotky a tagy pre zoznam článkov (blog, tag stránka) jedným dotazom — inak sa načítavali
     * dvoma dotazmi pre každý článok. Doctrine ich doplní do už načítaných entít.
     *
     * @param BlogPost[] $posts
     */
    public function preloadPhotosAndTags(array $posts): void
    {
        if ($posts === []) {
            return;
        }

        $this->createQueryBuilder('b')
            ->addSelect('p', 't')
            ->leftJoin('b.photos', 'p')
            ->leftJoin('b.tags', 't')
            ->andWhere('b IN (:posts)')
            ->setParameter('posts', $posts)
            ->addOrderBy('p.id', 'ASC') // post.photos.first = prvá nahraná fotka, ako doteraz
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