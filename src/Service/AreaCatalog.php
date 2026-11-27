<?php

namespace App\Service;

use App\Entity\Mushroom;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Pohoria a kraje, v ktorých sú schválené hríbiky — pre stránky /pohorie/{slug} a /kraj/{slug}.
 *
 * Nemajú vlastnú tabuľku: berú sa z polí hríbikov (dopĺňa app:resolve-locations), slug sa počíta
 * zo slovenského názvu („Nízke Tatry“ → nizke-tatry). Hríbikov sú stovky, takže to stačí.
 */
class AreaCatalog
{
    public const TYPE_RANGE = 'range';
    public const TYPE_REGION = 'region';

    /** Stránky s menej hríbikmi majú noindex a nie sú v sitemap (tenký obsah). */
    public const MIN_INDEXED = 2;

    private AsciiSlugger $slugger;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->slugger = new AsciiSlugger('sk');
    }

    public function slug(string $name): string
    {
        return $this->slugger->slug($name)->lower()->toString();
    }

    /**
     * @return list<array{slug: string, name: string, nameCs: string, count: int}> od najväčšieho počtu
     */
    public function ranges(): array
    {
        $rows = $this->em->createQuery(
            'SELECT m.mountainRange AS name, MAX(m.mountainRangeCs) AS nameCs, COUNT(m.id) AS cnt
             FROM App\Entity\Mushroom m
             WHERE m.published = true AND m.mountainRange IS NOT NULL
             GROUP BY m.mountainRange'
        )->getArrayResult();

        return $this->toAreas($rows);
    }

    /**
     * @return list<array{slug: string, name: string, nameCs: string, count: int, country: ?string}> od najväčšieho počtu
     */
    public function regions(): array
    {
        $rows = $this->em->createQuery(
            'SELECT m.region AS name, MAX(m.country) AS country, COUNT(m.id) AS cnt
             FROM App\Entity\Mushroom m
             WHERE m.published = true AND m.region IS NOT NULL
             GROUP BY m.region'
        )->getArrayResult();

        return $this->toAreas($rows);
    }

    /** @return array{slug: string, name: string, nameCs: string, count: int}|null */
    public function find(string $type, string $slug): ?array
    {
        foreach ($type === self::TYPE_RANGE ? $this->ranges() : $this->regions() as $area) {
            if ($area['slug'] === $slug) {
                return $area;
            }
        }

        return null;
    }

    /**
     * Schválené hríbiky v oblasti, aj s fotkami; od najvyššie položeného.
     *
     * @return Mushroom[]
     */
    public function mushrooms(string $type, string $name): array
    {
        $field = $type === self::TYPE_RANGE ? 'mountainRange' : 'region';
        $mushrooms = $this->em->createQueryBuilder()
            ->select('m', 'p', 'el')
            ->from(Mushroom::class, 'm')
            ->leftJoin('m.photos', 'p')
            // editLink je inverzná strana OneToOne — bez joinu by Doctrine robil dotaz pre každý hríbik.
            ->leftJoin('m.editLink', 'el')
            ->where('m.published = true')
            ->andWhere("m.$field = :name")->setParameter('name', $name)
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()->getResult();

        usort($mushrooms, fn (Mushroom $a, Mushroom $b) => [$b->getAltitude() ?? -1, $a->getTitle()] <=> [$a->getAltitude() ?? -1, $b->getTitle()]);

        return $mushrooms;
    }

    private function toAreas(array $rows): array
    {
        $areas = array_map(fn (array $r) => [
            'slug' => $this->slug($r['name']),
            'name' => $r['name'],
            'nameCs' => ($r['nameCs'] ?? null) ?: $r['name'],
            'count' => (int) $r['cnt'],
            'country' => $r['country'] ?? null,
        ], $rows);
        usort($areas, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        return $areas;
    }
}
