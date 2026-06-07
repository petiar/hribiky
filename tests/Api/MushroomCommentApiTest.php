<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MushroomCommentApiTest extends WebTestCase
{
    private const VALID_KEY = 'test-ios-key';
    private const INVALID_KEY = 'wrong-key';

    public function testGetCommentsWithoutApiKeyReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mushrooms_comments');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetCommentsWithInvalidApiKeyReturns403(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mushrooms_comments', [], [], ['HTTP_API-KEY' => self::INVALID_KEY]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetCommentsWithValidApiKeyReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mushrooms_comments', [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testGetCommentsFilteredByMushroomId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mushrooms_comments?mushroom_id=1', [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testCreateCommentWithoutApiKeyReturns401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/mushrooms_comments');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateCommentMissingRequiredFields(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/mushrooms_comments', ['name' => 'Test'], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertStringContainsString('mushroom_id', $data['message']);
    }
}