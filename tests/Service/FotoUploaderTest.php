<?php

namespace App\Tests\Service;

use App\Entity\Mushroom;
use App\Service\FotoUploader;
use App\Service\ImageLoader;
use App\Service\ImageOptimizer;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Celý tok nahrania fotky: presun → zmenšenie a odstránenie EXIF → náhľad.
 */
class FotoUploaderTest extends KernelTestCase
{
    private string $uploadDir;
    private string $sourceDir;
    private FotoUploader $uploader;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('Chýba rozšírenie GD.');
        }
        self::bootKernel();

        $base = sys_get_temp_dir() . '/hribiky-upload-' . uniqid();
        $this->uploadDir = $base . '/photos';
        $this->sourceDir = $base . '/source';
        mkdir($this->sourceDir, 0777, true);

        $loader = new ImageLoader();
        $this->uploader = new FotoUploader(
            $this->uploadDir,
            static::getContainer()->get(EntityManagerInterface::class),
            new ThumbnailService($this->uploadDir, $loader),
            new ImageOptimizer($loader),
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->uploadDir)) {
            exec('rm -rf ' . escapeshellarg(dirname($this->uploadDir)));
        }
        parent::tearDown();
    }

    public function testStoredPhotoIsOptimizedAndHasThumbnail(): void
    {
        $source = TestImages::jpeg($this->sourceDir . '/IMG_1234.jpg', 4000, 3000, quality: 98);
        $sourceSize = filesize($source);

        $filename = $this->uploader->store(new UploadedFile($source, 'IMG_1234.jpg', 'image/jpeg', null, true));

        $stored = $this->uploadDir . '/' . $filename;
        $this->assertFileExists($stored);
        $this->assertStringEndsWith('.jpg', $filename);
        [$width, $height] = getimagesize($stored);
        $this->assertSame([2000, 1500], [$width, $height]);
        $this->assertLessThan($sourceSize * 0.5, filesize($stored));

        $thumb = $this->uploadDir . '/thumbs/' . $filename;
        $this->assertFileExists($thumb);
        $this->assertSame(400, getimagesize($thumb)[0]);
    }

    public function testExifIsRemovedFromStoredPhotoAndThumbnailIsRotated(): void
    {
        if (!function_exists('exif_read_data')) {
            $this->markTestSkipped('Chýba rozšírenie exif.');
        }
        $source = TestImages::jpegWithExif($this->sourceDir . '/IMG.jpg', 600, 400, orientation: 6, make: 'TestPhone');

        $filename = $this->uploader->store(new UploadedFile($source, 'IMG.jpg', 'image/jpeg', null, true));

        $stored = $this->uploadDir . '/' . $filename;
        $this->assertStringNotContainsString('TestPhone', file_get_contents($stored));
        $this->assertSame([400, 600], array_slice(getimagesize($stored), 0, 2), 'Originál otočený podľa EXIF.');
        $this->assertSame([400, 600], array_slice(getimagesize($this->uploadDir . '/thumbs/' . $filename), 0, 2), 'Náhľad otočený rovnako.');
    }

    public function testStoreRefusesNonImage(): void
    {
        $path = $this->sourceDir . '/podvrh.jpg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        try {
            $this->uploader->store(new UploadedFile($path, 'foto.jpg', 'image/jpeg', null, true));
            $this->fail('store() mal odmietnuť súbor, ktorý nie je fotka.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame([], glob($this->uploadDir . '/*') ?: []);
    }

    public function testUploadAndAttachCreatesPhotosForMushroom(): void
    {
        $mushroom = (new Mushroom())->setTitle('Test')->setName('Tester')->setLatitude(48.1)->setLongitude(17.1);
        $files = [
            new UploadedFile(TestImages::jpeg($this->sourceDir . '/a.jpg', 3000, 2000), 'a.jpg', 'image/jpeg', null, true),
            new UploadedFile(TestImages::jpeg($this->sourceDir . '/b.jpg', 1000, 800), 'b.jpg', 'image/jpeg', null, true),
        ];

        $this->uploader->uploadAndAttach($files, $mushroom);

        $this->assertCount(2, $mushroom->getPhotos());
        foreach ($mushroom->getPhotos() as $photo) {
            $this->assertFileExists($this->uploadDir . '/' . $photo->getPath());
            $this->assertFileExists($this->uploadDir . '/thumbs/' . $photo->getPath());
            $this->assertLessThanOrEqual(ImageOptimizer::MAX_DIMENSION, max(array_slice(getimagesize($this->uploadDir . '/' . $photo->getPath()), 0, 2)));
        }
    }
}
