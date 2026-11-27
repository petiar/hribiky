<?php

namespace App\Tests\Api;

use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Chybné vstupy do API: vždy JSON s rozumným stavovým kódom, nikdy 500 ani HTML stránka.
 * (500 by navyše poslala e-mail o kritickej chybe.)
 */
class ApiErrorResponsesTest extends WebTestCase
{
    private const VALID_KEY = 'test-ios-key';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->catchExceptions(true);
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\MushroomComment c')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Mushroom m')->execute();
    }

    private function api(string $method, string $uri, array $params = []): array
    {
        $this->client->request($method, $uri, $params, [], ['HTTP_API-KEY' => self::VALID_KEY]);
        $response = $this->client->getResponse();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'), "$method $uri má vrátiť JSON, nie HTML");
        $this->assertLessThan(500, $response->getStatusCode(), "$method $uri nesmie skončiť chybou servera");

        return json_decode($response->getContent(), true) ?? [];
    }

    #[DataProvider('unknownResources')]
    public function testUnknownResourceReturnsJson404(string $uri): void
    {
        $data = $this->api('GET', $uri);

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('error', $data['status']);
        $this->assertSame('not_found', $data['code']);
    }

    public static function unknownResources(): array
    {
        return [
            'neexistujúci hríbik' => ['/api/mushrooms/999999'],
            'nečíselné ID hríbika' => ['/api/mushrooms/abc'],
            'neexistujúci komentár' => ['/api/mushrooms_comments/999999'],
            'neexistujúca adresa' => ['/api/neexistuje'],
        ];
    }

    #[DataProvider('invalidCoordinates')]
    public function testCreateMushroomWithInvalidCoordinatesReturns422(string $lat, string $lng, string $altitude = ''): void
    {
        $params = ['title' => 'Test', 'name' => 'Tester', 'latitude' => $lat, 'longitude' => $lng];
        if ($altitude !== '') {
            $params['altitude'] = $altitude;
        }
        $data = $this->api('POST', '/api/mushrooms', $params);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('error', $data['status']);
        $this->assertSame('invalid_field', $data['code']);
        $this->assertNull($data['id']);
        $this->assertSame(0, $this->em->getRepository(Mushroom::class)->count([]));
    }

    public static function invalidCoordinates(): array
    {
        return [
            'text v šírke' => ['abc', '17.1'],
            'prázdna dĺžka' => ['48.1', ''],
            'šírka mimo rozsahu' => ['91', '17.1'],
            'dĺžka mimo rozsahu' => ['48.1', '-181'],
            'text vo výške' => ['48.1', '17.1', 'vysoko'],
        ];
    }

    public function testCreateMushroomAcceptsCommaDecimalAndEmptyAltitude(): void
    {
        $this->api('POST', '/api/mushrooms', ['title' => 'Test', 'name' => 'Tester', 'latitude' => '48,1486', 'longitude' => ' 17.1077 ', 'altitude' => '']);

        $this->assertResponseStatusCodeSame(201);
        $mushroom = $this->em->getRepository(Mushroom::class)->findOneBy([]);
        $this->assertEqualsWithDelta(48.1486, $mushroom->getLatitude(), 0.00001);
        $this->assertEqualsWithDelta(17.1077, $mushroom->getLongitude(), 0.00001);
        $this->assertNull($mushroom->getAltitude());
    }

    #[DataProvider('unknownMushroomIds')]
    public function testCommentOnUnknownMushroomReturns404(string $mushroomId): void
    {
        $data = $this->api('POST', '/api/mushrooms_comments', ['name' => 'Tester', 'description' => 'Ahoj', 'mushroom_id' => $mushroomId]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('mushroom_not_found', $data['code']);
        $this->assertNull($data['id']);
        $this->assertSame(0, $this->em->getRepository(MushroomComment::class)->count([]));
    }

    public static function unknownMushroomIds(): array
    {
        return ['neexistujúce ID' => ['999999'], 'text' => ['abc'], 'prázdne' => ['']];
    }

    #[DataProvider('invalidNearby')]
    public function testNearbyWithInvalidInputReturns400(string $query): void
    {
        $data = $this->api('GET', '/api/mushrooms/nearby?' . $query);

        $this->assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('error', $data); // pôvodný formát chyby (appka ho môže čítať)
        $this->assertSame('error', $data['status']);
    }

    public static function invalidNearby(): array
    {
        return [
            'text v súradniciach' => ['latitude=abc&longitude=17.1'],
            'text v polomere' => ['latitude=48.1&longitude=17.1&radius=daleko'],
            'záporný polomer' => ['latitude=48.1&longitude=17.1&radius=-5'],
            'chýbajúce súradnice' => ['latitude=48.1'],
        ];
    }

    public function testInternalNearbyWithInvalidInputReturns400(): void
    {
        $this->client->request('GET', '/api/nearby?lat=abc&lng=17.1');

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Invalid coordinates', json_decode($this->client->getResponse()->getContent(), true)['error']);
    }

    public function testNearbyAcceptsZeroCoordinates(): void
    {
        // Rovník / nultý poludník — predtým „0“ vyhodnotilo ako chýbajúcu súradnicu.
        $this->api('GET', '/api/mushrooms/nearby?latitude=0&longitude=0');
        $this->assertResponseIsSuccessful();
    }
}
