<?php

namespace App\Tests\Api;

use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use App\Service\ImageOptimizer;
use App\Tests\Service\TestImages;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Nahrávanie fotiek cez API a web: priveľké fotky majú vrátiť zrozumiteľnú chybu (nie 500),
 * platné fotky sa majú uložiť zmenšené.
 */
class PhotoUploadApiTest extends WebTestCase
{
    private const VALID_KEY = 'test-ios-key';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $sourceDir;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('Chýba rozšírenie GD.');
        }
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\UserBadge b')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Photo p')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\MushroomComment c')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\MushroomEditLink l')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Mushroom m')->execute();

        $this->sourceDir = sys_get_temp_dir() . '/hribiky-upload-src-' . uniqid();
        mkdir($this->sourceDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->sourceDir));
        exec('rm -rf ' . escapeshellarg(static::getContainer()->getParameter('photos_directory')));
        parent::tearDown();
    }

    public function testApiTooLargePhotoReturns413AndSavesNothing(): void
    {
        $this->client->request('POST', '/api/mushrooms', $this->mushroomFields(), [
            'photos' => [$this->failedUpload('IMG_1234.jpg', UPLOAD_ERR_INI_SIZE)],
        ], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseStatusCodeSame(413);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertSame('photo_too_large', $data['code']);
        $this->assertStringContainsString('IMG_1234.jpg', $data['message']);
        $this->assertNull($data['id']);
        $this->assertSame(0, $this->em->getRepository(Mushroom::class)->count([]));
    }

    public function testApiValidPhotoIsStoredOptimized(): void
    {
        // Väčšia ako 2000 px (overí zmenšenie), ale s nízkou kvalitou, aby sa zmestila pod limit PHP.
        // Testovací klient simuluje upload_max_filesize — na GitHube je predvolených 2 MB.
        $photo = TestImages::jpeg($this->sourceDir . '/IMG.jpg', 2600, 1950, quality: 40); // ~1,1 MB
        $this->assertLessThan(UploadedFile::getMaxFilesize(), filesize($photo), sprintf(
            'Predpoklad testu: fotka (%d B) musí byť menšia ako upload_max_filesize (%d B).',
            filesize($photo),
            UploadedFile::getMaxFilesize()
        ));

        $this->client->request('POST', '/api/mushrooms', $this->mushroomFields(), [
            'photos' => [new UploadedFile($photo, 'IMG.jpg', 'image/jpeg', null, true)],
        ], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseStatusCodeSame(201);
        $id = json_decode($this->client->getResponse()->getContent(), true)['id'];
        $this->em->clear();
        $mushroom = $this->em->find(Mushroom::class, $id);
        $this->assertCount(1, $mushroom->getPhotos());

        $stored = static::getContainer()->getParameter('photos_directory') . '/' . $mushroom->getPhotos()->first()->getPath();
        $this->assertSame([ImageOptimizer::MAX_DIMENSION, 1500], array_slice(getimagesize($stored), 0, 2));
    }

    public function testApiSinglePhotoFieldWithoutBracketsWorks(): void
    {
        $photo = TestImages::jpeg($this->sourceDir . '/IMG.jpg', 800, 600);

        $this->client->request('POST', '/api/mushrooms', $this->mushroomFields(), [
            'photos' => new UploadedFile($photo, 'IMG.jpg', 'image/jpeg', null, true),
        ], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseStatusCodeSame(201);
    }

    public function testApiCommentTooLargePhotoReturns413(): void
    {
        $mushroomId = $this->createMushroom();

        $this->client->request('POST', '/api/mushrooms_comments', ['name' => 'Tester', 'mushroom_id' => $mushroomId], [
            'photo' => $this->failedUpload('big.jpg', UPLOAD_ERR_INI_SIZE),
        ], ['HTTP_API-KEY' => self::VALID_KEY]);

        $this->assertResponseStatusCodeSame(413);
        $this->assertSame('photo_too_large', json_decode($this->client->getResponse()->getContent(), true)['code']);
        $this->assertSame(0, $this->em->getRepository(MushroomComment::class)->count([]));
    }

    public function testWebCommentTooLargePhotoReturns413WithMessage(): void
    {
        $this->client->request('POST', '/rozcestnik-update', ['mushroom_comment' => ['name' => 'Tester']], [
            'mushroom_comment' => ['photos' => [$this->failedUpload('IMG_5555.jpg', UPLOAD_ERR_INI_SIZE)]],
        ]);

        $this->assertResponseStatusCodeSame(413);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Fotka „IMG_5555.jpg“ je príliš veľká', $data['errors'][0]);
    }

    public function testWebCommentInvalidFormReturns400NotSuccess(): void
    {
        // Predtým 200 → JavaScript ukázal „Komentár bol pridaný“, hoci sa nič neuložilo.
        $this->client->request('POST', '/rozcestnik-update', ['mushroom_comment' => ['name' => 'Tester']]);

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertIsArray($data['errors']);
        $this->assertNotEmpty($data['errors']);
    }

    public function testWebMushroomTooLargePhotoReturns413WithMessage(): void
    {
        $this->client->request('POST', '/rozcestnik/create', ['mushroom' => ['title' => 'Test']], [
            'mushroom' => ['photos' => [$this->failedUpload('IMG_7777.jpg', UPLOAD_ERR_INI_SIZE)]],
        ]);

        $this->assertResponseStatusCodeSame(413);
        $this->assertStringContainsString('IMG_7777.jpg', json_decode($this->client->getResponse()->getContent(), true)['errors'][0]);
    }

    // --- Pomocné metódy ---

    private function mushroomFields(): array
    {
        return ['title' => 'Test', 'name' => 'Tester', 'latitude' => '48.1486', 'longitude' => '17.1077'];
    }

    private function failedUpload(string $name, int $error): UploadedFile
    {
        return new UploadedFile(tempnam($this->sourceDir, 'up'), $name, 'image/jpeg', $error, true);
    }

    private function createMushroom(): int
    {
        $mushroom = (new Mushroom())->setTitle('Test')->setName('Tester')->setLatitude(48.1)->setLongitude(17.1)->setPublished(true);
        $this->em->persist($mushroom);
        $this->em->flush();

        return $mushroom->getId();
    }
}
