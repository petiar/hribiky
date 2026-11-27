<?php

namespace App\Service\Geo;

use App\Entity\Mushroom;

/**
 * Doplní hríbiku polohu slovami: obec, okres, kraj (Nominatim) a pohorie (lokálne dáta z OSM).
 */
class LocationResolver
{
    public function __construct(
        private readonly ReverseGeocoder $geocoder,
        private readonly MountainRangeLocator $mountainRangeLocator,
    ) {
    }

    /**
     * Nastaví údaje na entite (flush je na volajúcom). Pri chybe Nominatimu vyhodí výnimku
     * a hríbik ostane nespracovaný — skúsi sa znova pri ďalšom behu.
     */
    public function resolve(Mushroom $mushroom): void
    {
        $address = $this->geocoder->reverse($mushroom->getLatitude(), $mushroom->getLongitude());
        $mushroom->setAddress($address['locality'] ?? null, $address['district'] ?? null, $address['region'] ?? null);

        $range = $this->mountainRangeLocator->locate($mushroom->getLatitude(), $mushroom->getLongitude());
        $mushroom->setMountainRange($range['sk'] ?? null, $range['cs'] ?? null, $range['distance'] ?? null);

        // Krajinu dopĺňame len tam, kde chýba (staršie hríbiky) — zadanú hodnotu neprepisujeme.
        if ($mushroom->getCountry() === null && isset($address['country'])) {
            $mushroom->setCountry($address['country']);
        }

        $mushroom->setLocationResolvedAt(new \DateTimeImmutable());
    }
}
