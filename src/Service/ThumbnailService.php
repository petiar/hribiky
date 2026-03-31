<?php

namespace App\Service;

class ThumbnailService
{
    public const THUMB_WIDTH = 400;
    private const THUMB_SUBDIR = 'thumbs';

    public function __construct(private string $uploadDir) {}

    public function thumbExists(string $filename): bool
    {
        return file_exists($this->thumbPath($filename));
    }

    /** Vráti verejnú URL thumbu, ak existuje, inak originál. */
    public function publicUrl(string $filename): string
    {
        if ($this->thumbExists($filename)) {
            return '/uploads/photos/' . self::THUMB_SUBDIR . '/' . basename($filename);
        }
        return '/uploads/photos/' . basename($filename);
    }

    /** Zabezpečí, že thumb existuje – generuje ho ak treba. Vráti true pri úspechu. */
    public function ensure(string $filename): bool
    {
        if ($this->thumbExists($filename)) {
            return true;
        }
        return $this->generate($filename);
    }

    public function generate(string $filename): bool
    {
        $sourcePath = $this->uploadDir . '/' . basename($filename);
        if (!file_exists($sourcePath)) {
            return false;
        }

        $thumbDir = $this->uploadDir . '/' . self::THUMB_SUBDIR;
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $info = @getimagesize($sourcePath);
        if (!$info) {
            return false;
        }

        [$width, $height, $type] = $info;

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default        => false,
        };

        if (!$source) {
            return false;
        }

        // Oprav rotáciu podľa EXIF (typický problém s fotkami z telefónu)
        if ($type === IMAGETYPE_JPEG) {
            $source = $this->fixOrientation($source, $sourcePath);
            // Po rotácii sa mohli zameniť rozmery – aktualizujeme
            $width  = imagesx($source);
            $height = imagesy($source);
        }

        $destPath = $thumbDir . '/' . basename($filename);

        if ($width <= self::THUMB_WIDTH) {
            $result = $this->saveImage($source, $destPath, $type);
            imagedestroy($source);
            return $result;
        }

        $newHeight = (int) round($height * self::THUMB_WIDTH / $width);
        $thumb = imagescale($source, self::THUMB_WIDTH, $newHeight, IMG_BICUBIC);
        imagedestroy($source);

        if (!$thumb) {
            return false;
        }

        $result = $this->saveImage($thumb, $destPath, $type);
        imagedestroy($thumb);

        return $result;
    }

    private function saveImage(\GdImage $image, string $destPath, int $type): bool
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $destPath, 85),
            IMAGETYPE_PNG  => imagepng($image, $destPath, 7),
            IMAGETYPE_WEBP => imagewebp($image, $destPath, 85),
            default        => false,
        };
    }

    private function fixOrientation(\GdImage $image, string $sourcePath): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($sourcePath);
        $orientation = $exif['Orientation'] ?? 1;

        return match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function thumbPath(string $filename): string
    {
        return $this->uploadDir . '/' . self::THUMB_SUBDIR . '/' . basename($filename);
    }
}