<?php

namespace App\Tests\Api;

use App\Entity\Mushroom;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MushroomApiWithFixturesTest extends WebTestCase
{
    private const VALID_KEY = 'test-ios-key';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\Mushroom m')->execute();
    }

    private function createMushroom(string $title = 'Testovací hríbik', bool $published = true): Mushroom
    {
        $mushroom = (new Mushroom())
            ->setTitle($title)
            ->setName('Tester')
            ->setLatitude(48.1486)
            ->setLongitude(17.1077)
            ->setPublished($published);

        $this->em->persist($mushroom);
        $this->em->flush();

        return $mushroom;
    }

    public function testShowPublishedMushroomReturnsCorrectStructure(): void
    {
        $mushroom = $this->createMushroom();

        $this->client->request('GET', '/api/mushrooms/' . $mushroom->getId(), [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('latitude', $data);
        $this->assertArrayHasKey('longitude', $data);
        $this->assertArrayHasKey('photos', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('comments', $data);
        $this->assertSame($mushroom->getId(), $data['id']);
        $this->assertSame('Testovací hríbik', $data['title']);
    }

    public function testShowUnpublishedMushroomReturnsEmptyArray(): void
    {
        $mushroom = $this->createMushroom('Neschválený hríbik', false);

        $this->client->request('GET', '/api/mushrooms/' . $mushroom->getId(), [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEmpty($data);
    }

    public function testGetMushroomsWithSinceIdReturnsOnlyNewer(): void
    {
        $first = $this->createMushroom('Starý hríbik');
        $second = $this->createMushroom('Nový hríbik');

        $this->client->request('GET', '/api/mushrooms?since_id=' . $first->getId(), [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $ids = array_column($data, 'id');
        $this->assertContains($second->getId(), $ids);
        $this->assertNotContains($first->getId(), $ids);
    }

    public function testCreateMushroomSuccessfullyReturns201(): void
    {
        $this->client->request('POST', '/api/mushrooms', [
            'title'     => 'Nový hríbik z API',
            'name'      => 'Tester',
            'latitude'  => '48.1486',
            'longitude' => '17.1077',
            'email'     => 'test@example.com',
        ], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('ok', $data['status']);
        $this->assertArrayHasKey('id', $data);
        $this->assertIsInt($data['id']);

        $mushroom = $this->em->getRepository(Mushroom::class)->find($data['id']);
        $this->assertNotNull($mushroom);
        $this->assertFalse($mushroom->isPublished());
        $this->assertSame('api', $mushroom->getSource());
    }
}