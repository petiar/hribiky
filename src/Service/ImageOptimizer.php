<?php

namespace App\Service;

/**
 * Úprava nahranej fotky pred uložením:
 * - zmenšenie na najviac MAX_DIMENSION px na dlhšej strane,
 * - otočenie podľa EXIF orientácie,
 * - odstránenie metadát (EXIF: GPS, model telefónu, čas) — GD ich pri ukladaní nezapisuje,
 * - JPEG v kvalite JPEG_QUALITY.
 *
 * Fotka z telefónu (4000×3000, 3–7 MB) tak má typicky 300–500 kB.
 */
class ImageOptimizer
{
    public const MAX_DIMENSION = 2000;
    public const JPEG_QUALITY = 82;

    public function __construct(
        private readonly ImageLoader $imageLoader,
    ) {
    }

    /**
     * Upraví súbor na mieste. Vráti false, ak nejde o podporovaný obrázok
     * alebo úprava zlyhala — vtedy súbor ostane nezmenený.
     */
    public function optimize(string $path): bool
    {
        $loaded = $this->imageLoader->load($path);
        if ($loaded === null) {
            return false;
        }

        [$image, $type] = $loaded;
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);
        $optimized = $this->imageLoader->scaleDown($image, self::MAX_DIMENSION);
        $resized = imagesx($optimized) !== $originalWidth || imagesy($optimized) !== $originalHeight;

        // Najprv do dočasného súboru — pri chybe ostane originál nedotknutý.
        $tmpPath = $path . '.tmp';
        $saved = $this->imageLoader->save($optimized, $tmpPath, $type, self::JPEG_QUALITY);

        // Pamäť uvoľni hneď (pri veľkých fotkách desiatky MB); imagedestroy() je od PHP 8.0 bez efektu.
        unset($optimized, $image);

        if (!$saved || !is_file($tmpPath) || filesize($tmpPath) === 0) {
            @unlink($tmpPath);

            return false;
        }

        // PNG/WebP bez zmenšenia nahraď len vtedy, ak je výsledok menší (metadáta v nich bývajú zriedka).
        // JPEG nahrádzame vždy — kvôli odstráneniu EXIF (GPS, model telefónu) a otočeniu.
        if ($type !== IMAGETYPE_JPEG && !$resized && filesize($tmpPath) >= filesize($path)) {
            unlink($tmpPath);

            return true;
        }

        return rename($tmpPath, $path);
    }
}
