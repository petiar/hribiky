<?php

namespace App\Tests\Api;

use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MushroomCommentApiWithFixturesTest extends WebTestCase
{
    private const VALID_KEY = 'test-ios-key';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\MushroomComment c')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Mushroom m')->execute();
    }

    private function createMushroom(): Mushroom
    {
        $mushroom = (new Mushroom())
            ->setTitle('Hríbik')
            ->setName('Tester')
            ->setLatitude(48.1486)
            ->setLongitude(17.1077)
            ->setPublished(true);

        $this->em->persist($mushroom);
        $this->em->flush();

        return $mushroom;
    }

    private function createComment(Mushroom $mushroom, bool $published = true): MushroomComment
    {
        $comment = (new MushroomComment())
            ->setName('Komentátor')
            ->setDescription('Skvelý hríbik!')
            ->setPublished($published)
            ->setMushroom($mushroom);

        $this->em->persist($comment);
        $this->em->flush();

        return $comment;
    }

    public function testCreateCommentSuccessfullyReturns201(): void
    {
        $mushroom = $this->createMushroom();

        $this->client->request('POST', '/api/mushrooms_comments', [
            'name'        => 'Tester',
            'mushroom_id' => $mushroom->getId(),
            'description' => 'Skvelý hríbik, odporúčam!',
        ], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('ok', $data['status']);
        $this->assertArrayHasKey('id', $data);

        $comment = $this->em->getRepository(MushroomComment::class)->find($data['id']);
        $this->assertNotNull($comment);
        $this->assertFalse($comment->isPublished());
        $this->assertSame('api', $comment->getSource());
    }

    public function testShowPublishedCommentReturnsCorrectStructure(): void
    {
        $mushroom = $this->createMushroom();
        $comment = $this->createComment($mushroom);

        $this->client->request('GET', '/api/mushrooms_comments/' . $comment->getId(), [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertSame($comment->getId(), $data['id']);
    }

    public function testShowUnpublishedCommentReturnsEmptyArray(): void
    {
        $mushroom = $this->createMushroom();
        $comment = $this->createComment($mushroom, false);

        $this->client->request('GET', '/api/mushrooms_comments/' . $comment->getId(), [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEmpty($data);
    }

    public function testGetCommentsFilteredByMushroomIdReturnsOnlyThatMushroom(): void
    {
        $mushroom1 = $this->createMushroom();
        $mushroom2 = $this->createMushroom();
        $this->createComment($mushroom1);
        $this->createComment($mushroom2);

        $this->client->request('GET', '/api/mushrooms_comments?mushroom_id=' . $mushroom1->getId(), [], [], ['HTTP_API-KEY' => self::VALID_KEY]);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Komentátor', $data[0]['name']);
    }
}