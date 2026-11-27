<?php

namespace App\Service\Geo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Obec, okres a kraj zo súradníc — Nominatim (OpenStreetMap).
 *
 * Podmienky používania (https://operations.osmfoundation.org/policies/nominatim/): najviac 1 požiadavka
 * za sekundu (dodržiava app:resolve-locations) a User-Agent, ktorý identifikuje aplikáciu. Kontakt
 * sa dá doplniť cez NOMINATIM_USER_AGENT v .env.local na serveri.
 *
 * Názvy sú v jazyku krajiny (bez accept-language): slovenské obce po slovensky, české po česky.
 */
class ReverseGeocoder
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(NOMINATIM_USER_AGENT)%')]
        private readonly string $userAgent,
        #[Autowire('%env(NOMINATIM_URL)%')]
        private readonly string $url,
    ) {
    }

    /**
     * @return array{locality: ?string, district: ?string, region: ?string, country: ?string}|null null = bod nie je v žiadnej krajine (more…)
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface pri chybe siete alebo služby
     */
    public function reverse(float $latitude, float $longitude): ?array
    {
        $data = $this->httpClient->request('GET', $this->url, [
            'query' => [
                'format' => 'jsonv2',
                'lat' => $latitude,
                'lon' => $longitude,
                'zoom' => 14, // úroveň obce (katastra) — v horách dostaneme obec, do ktorej vrchol patrí
                'addressdetails' => 1,
            ],
            'headers' => ['User-Agent' => $this->userAgent],
            'timeout' => 15,
        ])->toArray();

        if (isset($data['error'])) {
            return null;
        }

        $address = $data['address'] ?? [];
        $first = static fn (string ...$keys): ?string => array_values(array_filter(array_map(fn ($k) => $address[$k] ?? null, $keys)))[0] ?? null;

        return [
            'locality' => $first('village', 'town', 'city', 'municipality'),
            // SK: district = „okres Brezno“, CZ: county = „okres Havlíčkův Brod“
            'district' => $first('district', 'county'),
            'region' => $first('region', 'state'),
            'country' => isset($address['country_code']) ? strtoupper($address['country_code']) : null,
        ];
    }
}
