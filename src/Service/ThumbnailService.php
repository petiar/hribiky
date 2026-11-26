<?php

namespace App\Service;

class ThumbnailService
{
    public const THUMB_WIDTH = 400;
    private const THUMB_SUBDIR = 'thumbs';

    public function __construct(
        private string $uploadDir,
        private ImageLoader $imageLoader,
    ) {}

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

        // Načíta a otočí podľa EXIF (typický problém s fotkami z telefónu).
        $loaded = $this->imageLoader->load($sourcePath);
        if ($loaded === null) {
            return false;
        }

        [$source, $type] = $loaded;
        $width = imagesx($source);
        $height = imagesy($source);
        $destPath = $thumbDir . '/' . basename($filename);

        if ($width <= self::THUMB_WIDTH) {
            $result = $this->imageLoader->save($source, $destPath, $type);
            return $result;
        }

        $newHeight = (int) round($height * self::THUMB_WIDTH / $width);
        $thumb = imagescale($source, self::THUMB_WIDTH, $newHeight, IMG_BICUBIC);

        if (!$thumb) {
            return false;
        }

        $result = $this->imageLoader->save($thumb, $destPath, $type);

        return $result;
    }

    private function thumbPath(string $filename): string
    {
        return $this->uploadDir . '/' . self::THUMB_SUBDIR . '/' . basename($filename);
    }
}