<?php

namespace App\Service;

/**
 * Načítanie a uloženie obrázkov cez GD — spoločné pre ImageOptimizer a ThumbnailService.
 */
class ImageLoader
{
    /**
     * Načíta JPEG/PNG/WebP a otočí ho podľa EXIF orientácie (fotky z telefónu).
     *
     * @return array{0: \GdImage, 1: int}|null [obrázok, IMAGETYPE_*]; null ak nejde o podporovaný obrázok
     */
    public function load(string $path): ?array
    {
        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }

        $type = $info[2];
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if (!$image) {
            return null;
        }

        if ($type === IMAGETYPE_JPEG) {
            $image = $this->fixOrientation($image, $path);
        }

        return [$image, $type];
    }

    /**
     * Uloží obrázok v danom formáte. GD pri ukladaní nezapisuje EXIF ani iné metadáta.
     */
    public function save(\GdImage $image, string $path, int $type, int $jpegQuality = 85): bool
    {
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            // Zachovaj priehľadnosť.
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, $jpegQuality),
            IMAGETYPE_PNG => imagepng($image, $path, 7),
            IMAGETYPE_WEBP => imagewebp($image, $path, $jpegQuality),
            default => false,
        };
    }

    /**
     * Zmenší obrázok tak, aby jeho dlhšia strana mala najviac $maxDimension px. Menší vráti nezmenený.
     */
    public function scaleDown(\GdImage $image, int $maxDimension): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $maxDimension) {
            return $image;
        }

        $ratio = $maxDimension / $longest;
        $scaled = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)), IMG_BICUBIC);

        return $scaled ?: $image;
    }

    private function fixOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }
}
