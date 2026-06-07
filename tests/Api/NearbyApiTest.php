<?php

namespace App\Tests\Api;

use App\Entity\Mushroom;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NearbyApiTest extends WebTestCase
{
    private const VALID_KEY = 'test-ios-key';

    // Súradnice: centrum Bratislavy
    private const LAT = 48.1486;
    private const LNG = 17.1077;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        // Vyčisti tabuľku pred každým testom
        $this->em->createQuery('DELETE FROM App\Entity\Mushroom m')->execute();

        // Fixture: publikovaný hríbik v centre Bratislavy
        $mushroom = (new Mushroom())
            ->setTitle('Hríbik pri Primaciálnom námestí')
            ->setName('Tester')
            ->setLatitude(self::LAT)
            ->setLongitude(self::LNG)
            ->setPublished(true);

        $this->em->persist($mushroom);
        $this->em->flush();
    }

    public function testNearbyReturnsMushroomWithinRadius(): void
    {
        // Súradnice ~10m od hríbika
        $this->client->request('GET', sprintf(
            '/api/mushrooms/nearby?latitude=%s&longitude=%s&radius=100',
            self::LAT + 0.00005,
            self::LNG + 0.00005,
        ), [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('hribiky', $data);
        $this->assertCount(1, $data['hribiky']);
        $this->assertSame('Hríbik pri Primaciálnom námestí', $data['hribiky'][0]['title']);
    }

    public function testNearbyDoesNotReturnMushroomOutsideRadius(): void
    {
        // Súradnice ~50km od hríbika
        $this->client->request('GET', sprintf(
            '/api/mushrooms/nearby?latitude=%s&longitude=%s&radius=100',
            self::LAT + 0.5,
            self::LNG + 0.5,
        ), [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('hribiky', $data);
        $this->assertEmpty($data['hribiky'], 'Returned: ' . json_encode($data['hribiky']));
    }

    public function testNearbyDoesNotReturnUnpublishedMushroom(): void
    {
        // Pridaj nepublikovaný hríbik na rovnakom mieste
        $unpublished = (new Mushroom())
            ->setTitle('Neschválený hríbik')
            ->setName('Tester')
            ->setLatitude(self::LAT)
            ->setLongitude(self::LNG)
            ->setPublished(false);

        $this->em->persist($unpublished);
        $this->em->flush();

        $this->client->request('GET', sprintf(
            '/api/mushrooms/nearby?latitude=%s&longitude=%s&radius=100',
            self::LAT,
            self::LNG,
        ), [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Len publikovaný sa má objaviť
        $this->assertCount(1, $data['hribiky']);
        $this->assertSame('Hríbik pri Primaciálnom námestí', $data['hribiky'][0]['title']);
    }
}