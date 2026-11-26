<?php

namespace App\Tests\Service;

use App\Service\ImageLoader;
use App\Service\ImageOptimizer;
use PHPUnit\Framework\TestCase;

class ImageOptimizerTest extends TestCase
{
    private string $dir;
    private ImageOptimizer $optimizer;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('Chýba rozšírenie GD.');
        }
        $this->dir = sys_get_temp_dir() . '/hribiky-optimizer-' . uniqid();
        mkdir($this->dir);
        $this->optimizer = new ImageOptimizer(new ImageLoader());
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*'));
            rmdir($this->dir);
        }
    }

    public function testLargeLandscapeIsScaledToMaxDimension(): void
    {
        $path = TestImages::jpeg($this->dir . '/a.jpg', 3000, 1500);

        $this->assertTrue($this->optimizer->optimize($path));

        $this->assertSame([2000, 1000], $this->dimensions($path));
    }

    public function testLargePortraitIsScaledByLongestSide(): void
    {
        $path = TestImages::jpeg($this->dir . '/a.jpg', 1500, 3000);

        $this->optimizer->optimize($path);

        $this->assertSame([1000, 2000], $this->dimensions($path));
    }

    public function testSmallImageKeepsDimensions(): void
    {
        $path = TestImages::jpeg($this->dir . '/a.jpg', 800, 600);

        $this->assertTrue($this->optimizer->optimize($path));

        $this->assertSame([800, 600], $this->dimensions($path));
    }

    public function testPhoneSizedPhotoGetsMuchSmaller(): void
    {
        $path = TestImages::jpeg($this->dir . '/a.jpg', 4000, 3000, quality: 98);
        $before = filesize($path);

        $this->optimizer->optimize($path);
        clearstatcache();

        $this->assertLessThan($before * 0.5, filesize($path), sprintf('%d B → %d B', $before, filesize($path)));
    }

    public function testExifIsRemovedAndOrientationApplied(): void
    {
        if (!function_exists('exif_read_data')) {
            $this->markTestSkipped('Chýba rozšírenie exif.');
        }
        // 300×200 s EXIF „otočiť o 90°“ — telefón ju zobrazí ako 200×300.
        $path = TestImages::jpegWithExif($this->dir . '/a.jpg', 300, 200, orientation: 6, make: 'TestPhone');
        $this->assertSame('TestPhone', exif_read_data($path)['Make'] ?? null, 'Predpoklad testu: EXIF je v súbore.');

        $this->optimizer->optimize($path);
        clearstatcache();

        $this->assertSame([200, 300], $this->dimensions($path));
        $exif = @exif_read_data($path);
        $this->assertNull($exif['Make'] ?? null, 'Model telefónu (EXIF) musí byť odstránený.');
        $this->assertNull($exif['Orientation'] ?? null);
        $this->assertStringNotContainsString('TestPhone', file_get_contents($path));
    }

    public function testPngKeepsFormatAndTransparency(): void
    {
        $path = $this->dir . '/a.png';
        $image = imagecreatetruecolor(3000, 100);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagepng($image, $path);

        $this->optimizer->optimize($path);

        $this->assertSame(IMAGETYPE_PNG, getimagesize($path)[2]);
        $this->assertSame([2000, 67], $this->dimensions($path));
        $result = imagecreatefrompng($path);
        $alpha = (imagecolorat($result, 10, 10) >> 24) & 0x7F;
        $this->assertSame(127, $alpha, 'Priehľadnosť musí zostať zachovaná.');
    }

    public function testNonImageIsLeftUntouched(): void
    {
        $path = $this->dir . '/a.jpg';
        file_put_contents($path, 'toto nie je obrázok');

        $this->assertFalse($this->optimizer->optimize($path));

        $this->assertSame('toto nie je obrázok', file_get_contents($path));
        $this->assertFileDoesNotExist($path . '.tmp');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dimensions(string $path): array
    {
        clearstatcache();
        $info = getimagesize($path);

        return [$info[0], $info[1]];
    }
}
