<?php

namespace App\Service\Geo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Pohorie (geomorfologický celok), v ktorom bod leží — z lokálneho súboru data/geomorfologia/units.json.
 *
 * Súbor je výrez z OpenStreetMap (boundary=geomorphological-unit, natural=mountain_range) bez vyšších
 * úrovní (Karpaty, provincie, oblasti) — ako ho vytvoriť nanovo, popisuje data/geomorfologia/README.md.
 * Jednotky do seba zapadajú (celok > podcelok > časť), preto vyberáme NAJVÄČŠIU, ktorá bod obsahuje:
 * pri Chopku je to „Nízke Tatry“, nie „Ďumbierske Tatry“ ani „Ďumbier“.
 *
 * Ak bod nie je v žiadnej jednotke (napr. kotliny v OSM chýbajú), vrátime najbližšiu do NEAR_METERS —
 * hríbik na Štrbskom Plese je tak „pod Tatrami“.
 */
class MountainRangeLocator
{
    public const NEAR_METERS = 1500;

    /** @var list<array{sk: string, cs: string, km2: int, bbox: array{float, float, float, float}, polygons: list<list<list<array{float, float}>>>}>|null */
    private ?array $units = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/data/geomorfologia/units.json')]
        private readonly string $dataFile,
    ) {
    }

    /**
     * @return array{sk: string, cs: string, distance: int}|null distance = 0, ak bod leží v pohorí, inak metre k hranici
     */
    public function locate(float $latitude, float $longitude): ?array
    {
        $units = $this->units();

        // Súbor je zoradený od najväčšej jednotky — prvá, ktorá bod obsahuje, je celok.
        foreach ($units as $unit) {
            if ($this->inBbox($unit['bbox'], $latitude, $longitude, 0) && $this->contains($unit['polygons'], $latitude, $longitude)) {
                return ['sk' => $unit['sk'], 'cs' => $unit['cs'], 'distance' => 0];
            }
        }

        $best = null;
        foreach ($units as $unit) {
            if (!$this->inBbox($unit['bbox'], $latitude, $longitude, self::NEAR_METERS)) {
                continue;
            }
            $distance = $this->distanceToPolygons($unit['polygons'], $latitude, $longitude);
            // Pri rovnakej vzdialenosti (celok a jeho podcelok majú spoločnú hranicu) vyhráva väčší — je skôr v zozname.
            if ($distance <= self::NEAR_METERS && ($best === null || $distance < $best['distance'] - 1)) {
                $best = ['sk' => $unit['sk'], 'cs' => $unit['cs'], 'distance' => max(1, (int) round($distance))];
            }
        }

        return $best;
    }

    private function units(): array
    {
        return $this->units ??= json_decode(file_get_contents($this->dataFile), true, flags: JSON_THROW_ON_ERROR)['units'];
    }

    /** bbox = [minLon, minLat, maxLon, maxLat]; $marginMeters zväčší rámik (na hľadanie blízkych pohorí). */
    private function inBbox(array $bbox, float $lat, float $lng, int $marginMeters): bool
    {
        $dLat = $marginMeters / 111_320;
        $dLng = $dLat / max(0.1, cos(deg2rad($lat)));

        return $lng >= $bbox[0] - $dLng && $lng <= $bbox[2] + $dLng && $lat >= $bbox[1] - $dLat && $lat <= $bbox[3] + $dLat;
    }

    /** Polygón = [vonkajší kruh, diera, diera…]; kruh = [[lon, lat], …]. */
    private function contains(array $polygons, float $lat, float $lng): bool
    {
        foreach ($polygons as $rings) {
            if (!$this->ringContains($rings[0], $lat, $lng)) {
                continue;
            }
            foreach (array_slice($rings, 1) as $hole) {
                if ($this->ringContains($hole, $lat, $lng)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /** Ray casting. */
    private function ringContains(array $ring, float $lat, float $lng): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /** Najkratšia vzdialenosť bodu k hranici v metroch (lokálna rovinná aproximácia — na pár km stačí). */
    private function distanceToPolygons(array $polygons, float $lat, float $lng): float
    {
        $kx = 111_320 * cos(deg2rad($lat));
        $ky = 110_540;
        $min = INF;
        foreach ($polygons as $rings) {
            foreach ($rings as $ring) {
                $n = count($ring);
                for ($i = 1; $i < $n; $i++) {
                    $ax = ($ring[$i - 1][0] - $lng) * $kx;
                    $ay = ($ring[$i - 1][1] - $lat) * $ky;
                    $bx = ($ring[$i][0] - $lng) * $kx;
                    $by = ($ring[$i][1] - $lat) * $ky;
                    $dx = $bx - $ax;
                    $dy = $by - $ay;
                    $len2 = $dx * $dx + $dy * $dy;
                    $t = $len2 > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $len2)) : 0;
                    $min = min($min, hypot($ax + $t * $dx, $ay + $t * $dy));
                }
            }
        }

        return $min;
    }
}
