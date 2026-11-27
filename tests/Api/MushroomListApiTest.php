<?php

namespace App\Tests\Api;

use App\Entity\Mushroom;
use App\Entity\MushroomEditLink;
use App\Entity\Photo;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * GET /api/mushrooms — zoznam a synchronizácia hríbikov.
 */
class MushroomListApiTest extends WebTestCase
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

    public function testWithoutLimitReturnsAllMushrooms(): void
    {
        // Viac ako pôvodný predvolený limit 100.
        $this->createMushrooms(105);

        $data = $this->list();

        $this->assertCount(105, $data);
    }

    public function testOrderIsNewestFirst(): void
    {
        $ids = $this->createMushrooms(3);

        $this->assertSame(array_reverse($ids), array_column($this->list(), 'id'));
    }

    public function testLimitStillWorks(): void
    {
        $ids = $this->createMushrooms(10);

        $data = $this->list(['limit' => 3]);

        $this->assertSame(array_slice(array_reverse($ids), 0, 3), array_column($data, 'id'));
    }

    public function testLimitZeroMeansAll(): void
    {
        $this->createMushrooms(4);

        $this->assertCount(4, $this->list(['limit' => 0]));
    }

    public function testSinceIdWithoutLimitReturnsAllNewer(): void
    {
        $ids = $this->createMushrooms(110);

        $data = $this->list(['since_id' => $ids[4]]); // novšie ako 5. hríbik → 105

        $this->assertCount(105, $data);
        $this->assertGreaterThan($ids[4], min(array_column($data, 'id')));
    }

    public function testLimitCountsMushroomsNotPhotos(): void
    {
        // Pri načítaní fotiek v jednom dotaze nesmie limit obmedziť riadky (hríbik × fotka).
        $ids = $this->createMushrooms(3, photosEach: 4);

        $data = $this->list(['limit' => 2]);

        $this->assertSame([$ids[2], $ids[1]], array_column($data, 'id'));
        foreach ($data as $item) {
            $this->assertCount(4, $item['photos']);
        }
    }

    public function testPhotosAreIncludedInUploadOrder(): void
    {
        $this->createMushrooms(1, photosEach: 3);

        $photos = $this->list()[0]['photos'];

        $this->assertCount(3, $photos);
        $this->assertStringEndsWith('foto-1.jpg', $photos[0]);
        $this->assertStringEndsWith('foto-3.jpg', $photos[2]);
    }

    public function testPhotoThumbnailsMatchPhotosOneToOne(): void
    {
        $this->createMushrooms(1, photosEach: 3);
        $dir = static::getContainer()->getParameter('photos_directory');
        // Fotka 1 má 800 px náhľad, fotka 2 len starší 400 px, fotka 3 žiadny.
        @mkdir($dir . '/' . ThumbnailService::THUMB_SUBDIR, 0777, true);
        @mkdir($dir . '/thumbs', 0777, true);
        touch($dir . '/' . ThumbnailService::THUMB_SUBDIR . '/h1-foto-1.jpg');
        touch($dir . '/thumbs/h1-foto-2.jpg');

        try {
            $item = $this->list()[0];

            $this->assertCount(count($item['photos']), $item['photo_thumbnails'], 'photo_thumbnails[i] patrí k photos[i]');
            $this->assertSame('http://localhost/uploads/photos/thumbs-800/h1-foto-1.jpg', $item['photo_thumbnails'][0]);
            $this->assertSame('http://localhost/uploads/photos/thumbs/h1-foto-2.jpg', $item['photo_thumbnails'][1]);
            $this->assertSame($item['photos'][2], $item['photo_thumbnails'][2], 'Bez náhľadu → originál');
        } finally {
            @unlink($dir . '/' . ThumbnailService::THUMB_SUBDIR . '/h1-foto-1.jpg');
            @unlink($dir . '/thumbs/h1-foto-2.jpg');
        }
    }

    public function testUnpublishedAreExcluded(): void
    {
        $this->createMushrooms(2);
        $this->createMushrooms(1, published: false);

        $this->assertCount(2, $this->list());
    }

    public function testMushroomWithEditLinkIsListed(): void
    {
        [$id] = $this->createMushrooms(1);
        $mushroom = $this->em->find(Mushroom::class, $id);
        $link = (new MushroomEditLink())
            ->setMushroom($mushroom)
            ->setTokenHash(hash('sha256', 'token'))
            ->setCreatedAt(new \DateTimeImmutable())
            ->setExpiresAt(new \DateTimeImmutable('+1 day'));
        $this->em->persist($link);
        $this->em->flush();
        $this->em->clear();

        $data = $this->list();

        $this->assertSame([$id], array_column($data, 'id'));
        $this->assertStringNotContainsString(hash('sha256', 'token'), $this->client->getResponse()->getContent());
    }

    // --- Pomocné metódy ---

    private function list(array $params = []): array
    {
        $this->client->request('GET', '/api/mushrooms', $params, [], ['HTTP_API-KEY' => self::VALID_KEY]);
        $this->assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    /**
     * @return int[] ID vytvorených hríbikov (vzostupne)
     */
    private function createMushrooms(int $count, int $photosEach = 0, bool $published = true): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $mushroom = (new Mushroom())
                ->setTitle('Hríbik ' . $i)
                ->setName('Tester')
                ->setLatitude(48.1486)
                ->setLongitude(17.1077)
                ->setPublished($published);
            $this->em->persist($mushroom);

            for ($p = 1; $p <= $photosEach; $p++) {
                $photo = (new Photo())
                    ->setPath(sprintf('h%d-foto-%d.jpg', $i, $p))
                    ->setOwner(Mushroom::class)
                    ->setMushroom($mushroom);
                $mushroom->addFotka($photo);
                $this->em->persist($photo);
            }

            $this->em->flush();
            $ids[] = $mushroom->getId();
        }
        $this->em->clear();

        return $ids;
    }
}
