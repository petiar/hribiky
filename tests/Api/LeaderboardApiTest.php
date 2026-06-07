<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LeaderboardApiTest extends WebTestCase
{
    public function testGetLeaderboardReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/leaderboard');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testGetLeaderboardDoesNotRequireApiKey(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/leaderboard');

        $this->assertResponseStatusCodeSame(200);
    }

    public function testGetLeaderboardItemStructure(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/leaderboard');

        $data = json_decode($client->getResponse()->getContent(), true);

        if (!empty($data)) {
            $first = $data[0];
            $this->assertArrayHasKey('name', $first);
            $this->assertArrayHasKey('mushroom_count', $first);
            $this->assertArrayHasKey('comment_count', $first);
            $this->assertArrayHasKey('score', $first);
        } else {
            $this->assertIsArray($data);
        }
    }
}