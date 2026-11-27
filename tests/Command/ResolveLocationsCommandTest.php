<?php

namespace App\Tests\Command;

use App\Command\ResolveLocationsCommand;
use App\Entity\Mushroom;
use App\Service\Geo\LocationResolver;
use App\Service\Geo\MountainRangeLocator;
use App\Service\Geo\ReverseGeocoder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Nominatim nahrádza MockHttpClient — žiadne skutočné requesty.
 */
class ResolveLocationsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    private function createMushroom(float $lat = 48.9436, float $lng = 19.5925, ?string $country = null): Mushroom
    {
        $mushroom = (new Mushroom())->setTitle('Chopok')->setName('Tester')->setLatitude($lat)->setLongitude($lng)->setCountry($country)->setPublished(true);
        $this->em->persist($mushroom);
        $this->em->flush();

        return $mushroom;
    }

    private function resolveLocations(MockHttpClient $http, array $input = []): CommandTester
    {
        $resolver = new LocationResolver(
            new ReverseGeocoder($http, 'Test/1.0', 'https://nominatim.example/reverse'),
            new MountainRangeLocator(static::getContainer()->getParameter('kernel.project_dir') . '/data/geomorfologia/units.json'),
        );
        $tester = new CommandTester(new ResolveLocationsCommand($this->em, $resolver, new NullLogger()));
        $tester->execute($input + ['--delay' => 0]);

        return $tester;
    }

    private function nominatim(): JsonMockResponse
    {
        return new JsonMockResponse(['address' => [
            'municipality' => 'Horná Lehota', 'district' => 'okres Brezno', 'region' => 'Banskobystrický kraj', 'country_code' => 'sk',
        ]]);
    }

    public function testFillsLocationAndMountainRange(): void
    {
        $mushroom = $this->createMushroom();
        $http = new MockHttpClient(fn () => $this->nominatim());

        $tester = $this->resolveLocations($http);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->em->refresh($mushroom);
        $this->assertSame('Horná Lehota', $mushroom->getLocality());
        $this->assertSame('okres Brezno', $mushroom->getDistrict());
        $this->assertSame('Banskobystrický kraj', $mushroom->getRegion());
        $this->assertSame('Nízke Tatry', $mushroom->getMountainRange());
        $this->assertSame(0, $mushroom->getMountainRangeDistance());
        $this->assertSame('SK', $mushroom->getCountry()); // chýbala — doplnená
        $this->assertNotNull($mushroom->getLocationResolvedAt());
    }

    public function testResolvedMushroomsAreSkippedUntilMoved(): void
    {
        $mushroom = $this->createMushroom(country: 'CZ');
        $http = new MockHttpClient(fn () => $this->nominatim());
        $this->resolveLocations($http);
        $requests = $http->getRequestsCount();

        $this->resolveLocations($http);
        $this->assertSame($requests, $http->getRequestsCount(), 'Spracovaný hríbik sa nemá posielať znova');
        $this->assertSame('CZ', $mushroom->getCountry(), 'Zadanú krajinu neprepisujeme');

        // Presunutý hríbik sa spracuje nanovo.
        $mushroom->setLatitude(48.95);
        $this->assertNull($mushroom->getLocationResolvedAt());
        $this->em->flush();
        $this->resolveLocations($http);
        $this->assertSame($requests + 1, $http->getRequestsCount());
    }

    public function testServiceErrorLeavesMushroomForNextRun(): void
    {
        $mushroom = $this->createMushroom();
        $http = new MockHttpClient(fn () => new MockResponse('', ['http_code' => 503]));

        $tester = $this->resolveLocations($http);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->em->refresh($mushroom);
        $this->assertNull($mushroom->getLocationResolvedAt());
    }
}
