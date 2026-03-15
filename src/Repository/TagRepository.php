<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\String\Slugger\AsciiSlugger;

class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * @return array<array{tag: Tag, lastmod: \DateTimeInterface}>
     */
    public function findAllWithPublishedPosts(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t', 'MAX(b.publishedAt) AS lastmod')
            ->join('t.blogPosts', 'b')
            ->andWhere('b.published = true')
            ->groupBy('t.id')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn($row) => [
            'tag' => $row[0],
            'lastmod' => new \DateTime($row['lastmod']),
        ], $rows);
    }

    public function findOrCreate(string $name): Tag
    {
        $slug = (new AsciiSlugger('sk'))->slug($name)->lower()->toString();

        $tag = $this->findOneBy(['slug' => $slug]);

        if (!$tag) {
            $tag = new Tag();
            $tag->setName($name);
            $this->getEntityManager()->persist($tag);
        }

        return $tag;
    }
}