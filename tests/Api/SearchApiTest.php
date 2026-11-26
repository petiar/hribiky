<?php

namespace App\Tests\Api;

use App\Controller\SearchApiController;
use App\Entity\BlogPost;
use App\Entity\Mushroom;
use App\Entity\Photo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pozn.: SQLite v testoch neignoruje diakritiku (MySQL áno) — dotazy sú bez diakritiky.
 */
class SearchApiTest extends WebTestCase
{
    private const VALID_KEY = 'test-ios-key';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\UserBadge b')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Photo p')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\MushroomComment c')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Mushroom m')->execute();
        $this->em->getConnection()->executeStatement('DELETE FROM blog_post_tag');
        $this->em->createQuery('DELETE FROM App\Entity\BlogPost b')->execute();
    }

    public function testResponseShape(): void
    {
        $this->createMushroom('Chopok');
        $this->createPost('Vylet na Chopok', 'vylet-na-chopok');

        $data = $this->search('chopok');

        $this->assertSame('chopok', $data['query']);
        $this->assertSame(2, $data['min_query_length']);
        $this->assertCount(1, $data['mushrooms']);
        $this->assertFalse($data['mushrooms_has_more']);
        $this->assertCount(1, $data['posts']);
        $this->assertFalse($data['posts_has_more']);
    }

    public function testMushroomHasSameShapeAsMushroomsList(): void
    {
        $mushroom = $this->createMushroom('Chopok');
        $this->addPhoto($mushroom);

        $fromSearch = $this->search('chopok')['mushrooms'][0];

        $this->client->request('GET', '/api/mushrooms', [], [], ['HTTP_API-KEY' => self::VALID_KEY]);
        $fromList = json_decode($this->client->getResponse()->getContent(), true)[0];

        // Appka zobrazí hríbik z vyhľadávania rovnakým kódom ako zo zoznamu.
        $this->assertSame($fromList, $fromSearch);
        $this->assertStringStartsWith('http', $fromSearch['photos'][0]);
    }

    public function testPostFields(): void
    {
        $post = $this->createPost('Vylet na Chopok', 'vylet-na-chopok', 'Kratky popis vyletu');
        $this->addPhoto($post);

        $item = $this->search('chopok')['posts'][0];

        $this->assertSame(['id', 'title', 'short_description', 'published_at', 'url', 'image_url'], array_keys($item));
        $this->assertSame('Vylet na Chopok', $item['title']);
        $this->assertSame('Kratky popis vyletu', $item['short_description']);
        $this->assertSame('2026-03-15T10:00:00+00:00', $item['published_at']);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/blog/vylet-na-chopok$#', $item['url']);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/uploads/photos/.+\.jpg$#', $item['image_url']);
    }

    public function testPostWithoutPhotoHasNullImage(): void
    {
        $this->createPost('Vylet na Chopok', 'vylet-na-chopok');

        $this->assertNull($this->search('chopok')['posts'][0]['image_url']);
    }

    public function testLimitAndHasMore(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->createMushroom('Chata ' . $i);
        }

        $data = $this->search('chata', ['limit' => 3]);
        $this->assertCount(3, $data['mushrooms']);
        $this->assertTrue($data['mushrooms_has_more']);

        $data = $this->search('chata', ['limit' => 4]);
        $this->assertCount(4, $data['mushrooms']);
        $this->assertFalse($data['mushrooms_has_more']);
    }

    public function testLimitIsCapped(): void
    {
        for ($i = 1; $i <= SearchApiController::MAX_LIMIT + 2; $i++) {
            $this->createMushroom('Chata ' . $i);
        }

        $data = $this->search('chata', ['limit' => 1000]);

        $this->assertCount(SearchApiController::MAX_LIMIT, $data['mushrooms']);
        $this->assertTrue($data['mushrooms_has_more']);
    }

    public function testShortQueryReturnsEmptyResults(): void
    {
        $this->createMushroom('Chopok');

        $data = $this->search('c');

        $this->assertSame([], $data['mushrooms']);
        $this->assertSame([], $data['posts']);
    }

    public function testUnpublishedAreExcludedAndNoPrivateData(): void
    {
        $this->createMushroom('Chopok schvaleny');
        $this->createMushroom('Chopok neschvaleny', published: false);

        $data = $this->search('chopok');

        $this->assertSame(['Chopok schvaleny'], array_column($data['mushrooms'], 'title'));
        $this->assertStringNotContainsString('autor@example.com', $this->client->getResponse()->getContent());
    }

    public function testDoesNotRequireApiKey(): void
    {
        $this->client->request('GET', '/api/search', ['q' => 'chopok']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    // --- Pomocné metódy ---

    private function search(string $query, array $params = []): array
    {
        $this->client->request('GET', '/api/search', ['q' => $query] + $params);
        $this->assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function createMushroom(string $title, bool $published = true): Mushroom
    {
        $mushroom = (new Mushroom())
            ->setTitle($title)
            ->setName('Tester')
            ->setEmail('autor@example.com')
            ->setDescription('Popis.')
            ->setLatitude(48.1486)
            ->setLongitude(17.1077)
            ->setAltitude(2024)
            ->setPublished($published);

        $this->em->persist($mushroom);
        $this->em->flush();

        return $mushroom;
    }

    private function createPost(string $title, string $slug, string $shortDescription = 'Popis'): BlogPost
    {
        $post = (new BlogPost())
            ->setTitle($title)
            ->setSlug($slug)
            ->setShortDescription($shortDescription)
            ->setText('Text')
            ->setPublished(true)
            ->setPublishedAt(new \DateTime('2026-03-15 10:00:00'));

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    private function addPhoto(Mushroom|BlogPost $owner): void
    {
        $photo = (new Photo())->setPath(uniqid() . '.jpg')->setOwner($owner::class);
        if ($owner instanceof Mushroom) {
            $photo->setMushroom($owner);
            $owner->addFotka($photo);
        } else {
            $photo->setBlogPost($owner);
        }

        $this->em->persist($photo);
        $this->em->flush();
        $this->em->clear(); // aby sa fotky načítali z DB rovnako ako v reálnej požiadavke
    }
}
