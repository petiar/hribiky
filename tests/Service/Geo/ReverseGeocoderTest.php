<?php

namespace App\Tests\Service\Geo;

use App\Service\Geo\ReverseGeocoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class ReverseGeocoderTest extends TestCase
{
    private function geocoder(MockHttpClient $http): ReverseGeocoder
    {
        return new ReverseGeocoder($http, 'Test/1.0', 'https://nominatim.example/reverse');
    }

    public function testSlovakAddress(): void
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertStringContainsString('lat=48.94', $url);
            $this->assertStringContainsString('zoom=14', $url);
            $this->assertContains('User-Agent: Test/1.0', $options['headers']);

            return new JsonMockResponse(['address' => [
                'municipality' => 'Horná Lehota', 'district' => 'okres Brezno', 'region' => 'Banskobystrický kraj', 'country_code' => 'sk',
            ]]);
        });

        $this->assertSame(
            ['locality' => 'Horná Lehota', 'district' => 'okres Brezno', 'region' => 'Banskobystrický kraj', 'country' => 'SK'],
            $this->geocoder($http)->reverse(48.94, 19.59),
        );
    }

    public function testCzechAddressUsesCountyAndPrefersTownOverHamlet(): void
    {
        $http = new MockHttpClient(new JsonMockResponse(['address' => [
            'hamlet' => 'Vlkovsko', 'town' => 'Havlíčkův Brod', 'county' => 'okres Havlíčkův Brod', 'region' => 'Kraj Vysočina', 'country_code' => 'cz',
        ]]));

        $this->assertSame(
            ['locality' => 'Havlíčkův Brod', 'district' => 'okres Havlíčkův Brod', 'region' => 'Kraj Vysočina', 'country' => 'CZ'],
            $this->geocoder($http)->reverse(49.62, 15.57),
        );
    }

    public function testUnknownPlaceReturnsNull(): void
    {
        $http = new MockHttpClient(new JsonMockResponse(['error' => 'Unable to geocode']));

        $this->assertNull($this->geocoder($http)->reverse(0, 0));
    }

    public function testServerErrorThrows(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 503]));

        $this->expectException(\Symfony\Contracts\HttpClient\Exception\ExceptionInterface::class);
        $this->geocoder($http)->reverse(48.94, 19.59);
    }
}
