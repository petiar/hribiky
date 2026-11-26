<?php

namespace App\Service;

use App\Entity\BlogPost;
use App\Entity\Mushroom;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Fulltextové vyhľadávanie v hríbikoch a blogových článkoch (len publikované).
 *
 * - Každé slovo z dotazu musí byť nájdené (v ktoromkoľvek prehľadávanom poli).
 * - Zhoda v názve má prednosť, potom novšie záznamy.
 * - Diakritiku a veľkosť písmen ignoruje MySQL kolácia utf8mb4_unicode_ci („bela“ nájde „Belá“).
 */
class SearchService
{
    public const MIN_QUERY_LENGTH = 2;
    private const MAX_QUERY_LENGTH = 100;
    private const MAX_WORDS = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Upraví dotaz od používateľa: orezanie, zlúčenie medzier, obmedzenie dĺžky.
     */
    public function normalizeQuery(?string $query): string
    {
        $query = trim(preg_replace('/\s+/u', ' ', (string) $query));

        return mb_substr($query, 0, self::MAX_QUERY_LENGTH);
    }

    public function isQueryValid(string $normalizedQuery): bool
    {
        return mb_strlen($normalizedQuery) >= self::MIN_QUERY_LENGTH;
    }

    /**
     * @return Mushroom[]
     */
    public function searchMushrooms(string $query, int $limit): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(Mushroom::class, 'm')
            ->andWhere('m.published = true');

        return $this->applySearch($qb, 'm', ['m.title', 'm.description'], 'm.title', 'm.createdAt', $query, $limit);
    }

    /**
     * @return BlogPost[]
     */
    public function searchBlogPosts(string $query, int $limit): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(BlogPost::class, 'b')
            ->andWhere('b.published = true');

        return $this->applySearch($qb, 'b', ['b.title', 'b.shortDescription', 'b.text'], 'b.title', 'b.publishedAt', $query, $limit);
    }

    /**
     * @param string[] $fields prehľadávané polia
     */
    private function applySearch(
        QueryBuilder $qb,
        string $alias,
        array $fields,
        string $titleField,
        string $dateField,
        string $query,
        int $limit,
    ): array {
        $words = $this->splitWords($query);
        if ($words === []) {
            return [];
        }

        foreach ($words as $i => $word) {
            $param = 'w' . $i;
            $conditions = array_map(fn(string $field) => sprintf("%s LIKE :%s ESCAPE '!'", $field, $param), $fields);
            $qb->andWhere('(' . implode(' OR ', $conditions) . ')')
                ->setParameter($param, '%' . $this->escapeLike($word) . '%');
        }

        // Poradie: presný názov → názov začína dotazom → dotaz kdekoľvek v názve → zhoda len v texte; potom novšie.
        $qb->addSelect(sprintf(
                "(CASE WHEN %1\$s = :exactQuery THEN 0"
                . " WHEN %1\$s LIKE :prefixQuery ESCAPE '!' THEN 1"
                . " WHEN %1\$s LIKE :fullQuery ESCAPE '!' THEN 2"
                . " ELSE 3 END) AS HIDDEN titleRank",
                $titleField
            ))
            ->setParameter('exactQuery', $query)
            ->setParameter('prefixQuery', $this->escapeLike($query) . '%')
            ->setParameter('fullQuery', '%' . $this->escapeLike($query) . '%')
            ->orderBy('titleRank', 'ASC')
            ->addOrderBy($dateField, 'DESC')
            ->addOrderBy($alias . '.id', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return string[]
     */
    private function splitWords(string $query): array
    {
        $words = array_values(array_filter(
            explode(' ', $query),
            fn(string $word) => mb_strlen($word) >= self::MIN_QUERY_LENGTH
        ));

        return array_slice(array_unique($words), 0, self::MAX_WORDS);
    }

    /**
     * Znaky % a _ v dotaze sa hľadajú doslovne, nie ako zástupné znaky LIKE.
     */
    private function escapeLike(string $value): string
    {
        // „!“ je escape znak (ESCAPE '!'), funguje rovnako v MySQL aj SQLite.
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
