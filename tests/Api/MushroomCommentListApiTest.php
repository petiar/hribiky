<?php

namespace App\Tests\Api;

use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use App\Entity\Photo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * GET /api/mushrooms_comments — zoznam a synchronizácia komentárov.
 */
class MushroomCommentListApiTest extends WebTestCase
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
        $this->em->createQuery('DELETE FROM App\Entity\MushroomEditLink l')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Mushroom m')->execute();
    }

    public function testWithoutLimitReturnsAllComments(): void
    {
        // Viac ako pôvodný predvolený limit 100.
        $this->createComments($this->createMushroom(), 105);

        $this->assertCount(105, $this->list());
    }

    public function testOrderIsNewestFirstAndShape(): void
    {
        $ids = $this->createComments($this->createMushroom(), 3, photosEach: 1);

        $data = $this->list();

        $this->assertSame(array_reverse($ids), array_column($data, 'id'));
        $this->assertSame(['id', 'name', 'description', 'photos', 'photo_thumbnails', 'createdAt'], array_keys($data[0]));
        $this->assertStringStartsWith('http', $data[0]['photos'][0]);
    }

    public function testLimitStillWorks(): void
    {
        $ids = $this->createComments($this->createMushroom(), 10);

        $this->assertSame(array_slice(array_reverse($ids), 0, 3), array_column($this->list(['limit' => 3]), 'id'));
    }

    public function testLimitCountsCommentsNotPhotos(): void
    {
        $ids = $this->createComments($this->createMushroom(), 3, photosEach: 4);

        $data = $this->list(['limit' => 2]);

        $this->assertSame([$ids[2], $ids[1]], array_column($data, 'id'));
        foreach ($data as $item) {
            $this->assertCount(4, $item['photos']);
        }
    }

    public function testSinceIdWithoutLimitReturnsAllNewer(): void
    {
        $ids = $this->createComments($this->createMushroom(), 110);

        $data = $this->list(['since_id' => $ids[4]]);

        $this->assertCount(105, $data);
        $this->assertGreaterThan($ids[4], min(array_column($data, 'id')));
    }

    public function testMushroomIdFilter(): void
    {
        $a = $this->createMushroom();
        $b = $this->createMushroom();
        $idsA = $this->createComments($a, 2);
        $this->createComments($b, 3);

        $this->assertSame(array_reverse($idsA), array_column($this->list(['mushroom_id' => $a]), 'id'));
    }

    public function testUnpublishedAreExcluded(): void
    {
        $mushroom = $this->createMushroom();
        $this->createComments($mushroom, 2);
        $this->createComments($mushroom, 1, published: false);

        $this->assertCount(2, $this->list());
    }

    // --- Pomocné metódy ---

    private function list(array $params = []): array
    {
        $this->client->request('GET', '/api/mushrooms_comments', $params, [], ['HTTP_API-KEY' => self::VALID_KEY]);
        $this->assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function createMushroom(): int
    {
        $mushroom = (new Mushroom())
            ->setTitle('Hríbik')
            ->setName('Tester')
            ->setLatitude(48.1486)
            ->setLongitude(17.1077)
            ->setPublished(true);
        $this->em->persist($mushroom);
        $this->em->flush();

        return $mushroom->getId();
    }

    /**
     * @return int[] ID vytvorených komentárov (vzostupne)
     */
    private function createComments(int $mushroomId, int $count, int $photosEach = 0, bool $published = true): array
    {
        $mushroom = $this->em->getReference(Mushroom::class, $mushroomId);
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $comment = (new MushroomComment())
                ->setName('Komentátor')
                ->setEmail('komentator@example.com')
                ->setDescription('Komentár ' . $i)
                ->setPublished($published)
                ->setMushroom($mushroom);
            $this->em->persist($comment);

            for ($p = 1; $p <= $photosEach; $p++) {
                $photo = (new Photo())
                    ->setPath(sprintf('k%d-foto-%d.jpg', $i, $p))
                    ->setOwner(MushroomComment::class)
                    ->setMushroomComment($comment);
                $comment->addFotka($photo);
                $this->em->persist($photo);
            }

            $this->em->flush();
            $ids[] = $comment->getId();
        }
        $this->em->clear();

        return $ids;
    }
}
