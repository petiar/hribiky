<?php

namespace App\Tests\Service;

/**
 * Pomocník pre testy: vyrobí JPEG s „fotografickým“ obsahom, voliteľne s EXIF blokom.
 */
final class TestImages
{
    /**
     * JPEG s plynulými prechodmi farieb a šumom (komprimuje sa podobne ako fotka, nie ako jednofarebná plocha).
     */
    public static function jpeg(string $path, int $width, int $height, int $quality = 90): string
    {
        // Malý náhodný obrázok zväčšený na cieľovú veľkosť = plynulé prechody ako na fotke.
        $small = imagecreatetruecolor(40, 30);
        mt_srand(42);
        for ($x = 0; $x < 40; $x++) {
            for ($y = 0; $y < 30; $y++) {
                imagesetpixel($small, $x, $y, imagecolorallocate($small, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        $image = imagecreatetruecolor($width, $height);
        imagecopyresampled($image, $small, 0, 0, 0, 0, $width, $height, 40, 30);
        // Trochu jemných detailov (ako textúra na fotke).
        for ($i = 0; $i < 2000; $i++) {
            imageline($image, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height),
                imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }

        imagejpeg($image, $path, $quality);

        return $path;
    }

    /**
     * JPEG s EXIF blokom (APP1) obsahujúcim Orientation a Make — ako fotka z telefónu.
     */
    public static function jpegWithExif(string $path, int $width, int $height, int $orientation, string $make): string
    {
        self::jpeg($path, $width, $height);
        $jpeg = file_get_contents($path);

        $makeValue = $make . "\0";
        // TIFF hlavička (little endian) + IFD0 s 2 položkami (Make, Orientation), za ním text Make.
        $ifdOffset = 8;
        $dataOffset = $ifdOffset + 2 + 2 * 12 + 4;
        $tiff = 'II' . pack('v', 42) . pack('V', $ifdOffset)
            . pack('v', 2)
            . pack('vvVV', 0x010F, 2, strlen($makeValue), $dataOffset)   // Make (ASCII)
            . pack('vvV', 0x0112, 3, 1) . pack('vv', $orientation, 0)    // Orientation (SHORT)
            . pack('V', 0)                                              // ďalší IFD: žiadny
            . $makeValue;

        $app1 = "Exif\0\0" . $tiff;
        $segment = "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1;

        // Vlož hneď za SOI (FFD8).
        file_put_contents($path, substr($jpeg, 0, 2) . $segment . substr($jpeg, 2));

        return $path;
    }
}
