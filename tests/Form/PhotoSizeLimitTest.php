<?php

namespace App\Tests\Form;

use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use App\Form\MushroomCommentType;
use App\Form\MushroomType;
use App\Tests\Service\TestImages;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Limit veľkosti fotky vo webových formulároch (FotoUploader::MAX_FILE_SIZE = 20M).
 * Pôvodne 8 MB — fotky z novších telefónov (10–20 MB) formulár odmietal.
 */
class PhotoSizeLimitTest extends KernelTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('Chýba rozšírenie GD.');
        }
        self::bootKernel();
        $this->dir = sys_get_temp_dir() . '/hribiky-limit-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    public static function forms(): array
    {
        return [
            'hríbik' => [MushroomType::class, Mushroom::class],
            'komentár' => [MushroomCommentType::class, MushroomComment::class],
        ];
    }

    #[DataProvider('forms')]
    public function testPhotoOverOldLimitIsAccepted(string $formType, string $entity): void
    {
        $this->assertSame([], $this->photoErrors($formType, $entity, $this->jpegOfSize(12_000_000)));
    }

    #[DataProvider('forms')]
    public function testPhotoOverNewLimitIsRejected(string $formType, string $entity): void
    {
        $errors = $this->photoErrors($formType, $entity, $this->jpegOfSize(21_000_000));

        $this->assertCount(1, $errors);
    }

    /**
     * @return string[] chyby poľa photos
     */
    private function photoErrors(string $formType, string $entity, string $path): array
    {
        $form = static::getContainer()->get('form.factory')->create($formType, new $entity(), ['csrf_protection' => false]);
        $form->submit(['photos' => [new UploadedFile($path, 'IMG.jpg', 'image/jpeg', null, true)]], false);

        return array_map(fn($error) => $error->getMessage(), iterator_to_array($form->get('photos')->getErrors(true)));
    }

    /**
     * Platný JPEG s presnou veľkosťou (za koniec obrázka sa doplnia bajty — typ súboru ostane image/jpeg).
     */
    private function jpegOfSize(int $bytes): string
    {
        $path = TestImages::jpeg($this->dir . '/' . uniqid() . '.jpg', 200, 150);
        $handle = fopen($path, 'ab');
        fwrite($handle, str_repeat("\0", $bytes - filesize($path)));
        fclose($handle);
        clearstatcache();

        return $path;
    }
}
