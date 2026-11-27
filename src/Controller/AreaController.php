<?php

namespace App\Controller;

use App\Entity\Mushroom;
use App\Service\AreaCatalog;
use App\Service\MapMarkers;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stránky pohorí a krajov — zoznam a mapa hríbikov v oblasti (SEO: „turistické rozcestníky Nízke Tatry“).
 * Adresy sú rovnaké na .sk aj .cz (hreflang v base.html.twig berie rovnakú cestu).
 */
class AreaController extends AbstractController
{
    public function __construct(
        private readonly AreaCatalog $catalog,
        private readonly MapMarkers $mapMarkers,
    ) {
    }

    #[Route('/pohoria-a-kraje', name: 'area_index')]
    public function index(): Response
    {
        return $this->render('area/index.html.twig', [
            'ranges' => $this->catalog->ranges(),
            'regions' => $this->catalog->regions(),
        ]);
    }

    #[Route('/pohorie/{slug}', name: 'area_range', requirements: ['slug' => '[a-z0-9-]+'])]
    public function range(string $slug): Response
    {
        return $this->show(AreaCatalog::TYPE_RANGE, $slug);
    }

    #[Route('/kraj/{slug}', name: 'area_region', requirements: ['slug' => '[a-z0-9-]+'])]
    public function region(string $slug): Response
    {
        return $this->show(AreaCatalog::TYPE_REGION, $slug);
    }

    private function show(string $type, string $slug): Response
    {
        $area = $this->catalog->find($type, $slug) ?? throw $this->createNotFoundException('Oblasť nenájdená');
        $mushrooms = $this->catalog->mushrooms($type, $area['name']);

        // Súvisiace oblasti: pri pohorí kraje, v ktorých leží, pri kraji pohoria v ňom (s počtom hríbikov).
        $related = [];
        foreach ($mushrooms as $mushroom) {
            $name = $type === AreaCatalog::TYPE_RANGE ? $mushroom->getRegion() : $mushroom->getMountainRange();
            if ($name !== null) {
                $related[$name] ??= [
                    'name' => $name,
                    'nameCs' => $type === AreaCatalog::TYPE_RANGE ? $name : ($mushroom->getMountainRangeCs() ?: $name),
                    'slug' => $this->catalog->slug($name),
                    'count' => 0,
                ];
                $related[$name]['count']++;
            }
        }
        usort($related, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        $localities = array_count_values(array_filter(array_map(fn (Mushroom $m) => $m->getLocality(), $mushrooms)));
        arsort($localities);
        $withAltitude = array_filter($mushrooms, fn (Mushroom $m) => $m->getAltitude() !== null);

        return $this->render('area/show.html.twig', [
            'type' => $type,
            'area' => $area,
            'mushrooms' => $mushrooms,
            'mushroomsJson' => $this->mapMarkers->toJson($mushrooms),
            'related' => $related,
            'localities' => array_slice(array_keys($localities), 0, 8),
            'highest' => $withAltitude ? reset($withAltitude) : null, // zoradené od najvyššieho
            'indexable' => count($mushrooms) >= AreaCatalog::MIN_INDEXED,
        ]);
    }
}
