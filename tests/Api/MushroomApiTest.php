<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MushroomApiTest extends WebTestCase
{
    private const VALID_KEY = 'test-ios-key';
    private const INVALID_KEY = 'wrong-key';

    public function testGetMushroomsWithoutApiKeyReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mushrooms');

        $this->assertResponseStatusCodeSame(401);
        $this->assertJson($client->getResponse()->getContent());
    }

    public function testGetMushroomsWithInvalidApiKeyReturns403(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mushrooms', [], [], ['HTTP_API-KEY' => self::INVALID_KEY]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetMushroomsWithValidApiKeyReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mushrooms', [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testGetMushroomsWithLimitParam(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mushrooms?limit=5', [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertLessThanOrEqual(5, count($data));
    }

    public function testShowMushroomNotFoundReturns404(): void
    {
        $client = static::createClient();
        $client->catchExceptions(true);
        $client->request('GET', '/api/mushrooms/999999', [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateMushroomWithoutApiKeyReturns401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/mushrooms');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateMushroomMissingRequiredFieldsReturns422(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/mushrooms', ['title' => 'Test'], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertStringContainsString('latitude', $data['message']);
    }

    public function testNearbyEndpointMissingCoordinatesReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mushrooms/nearby', [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testNearbyEndpointWithCoordinatesReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mushrooms/nearby?latitude=48.1&longitude=17.1', [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('hribiky', $data);
    }
}