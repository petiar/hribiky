<?php

namespace App\Service;

/**
 * Náhľady fotiek (800 px na šírku, JPEG kvalita 80 — ostré aj na retina displeji, ~2,5× menšie než originál).
 * Používa ich web (kartičky, mapa, galérie) aj API (`photo_thumbnails` pre appku).
 *
 * Náhľady sa vytvárajú pri nahraní fotky (FotoUploader) a príkazom `app:generate-thumbnails`
 * (spúšťa ho deploy). Pôvodné 400 px náhľady v `thumbs/` slúžia len ako záloha, kým sa nové
 * nevygenerujú — adresár sa dá neskôr zmazať.
 */
class ThumbnailService
{
    public const THUMB_WIDTH = 800;
    public const THUMB_QUALITY = 80;
    public const THUMB_SUBDIR = 'thumbs-800';
    /** Staršie 400 px náhľady — záloha pre publicUrl(), kým nie sú vygenerované nové. */
    private const LEGACY_SUBDIR = 'thumbs';

    public function __construct(
        private string $uploadDir,
        private ImageLoader $imageLoader,
    ) {}

    public function thumbExists(string $filename): bool
    {
        return file_exists($this->thumbPath($filename));
    }

    /**
     * Verejná URL náhľadu (relatívna, od koreňa webu): 800 px náhľad, inak starší 400 px, inak originál.
     * Nič negeneruje — len kontroluje existenciu súborov.
     */
    public function publicUrl(string $filename): string
    {
        $name = basename($filename);
        if ($this->thumbExists($name)) {
            return '/uploads/photos/' . self::THUMB_SUBDIR . '/' . $name;
        }
        if (file_exists($this->uploadDir . '/' . self::LEGACY_SUBDIR . '/' . $name)) {
            return '/uploads/photos/' . self::LEGACY_SUBDIR . '/' . $name;
        }
        return '/uploads/photos/' . $name;
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
            return $this->imageLoader->save($source, $destPath, $type, self::THUMB_QUALITY);
        }

        $newHeight = (int) round($height * self::THUMB_WIDTH / $width);
        $thumb = imagescale($source, self::THUMB_WIDTH, $newHeight, IMG_BICUBIC);

        if (!$thumb) {
            return false;
        }

        return $this->imageLoader->save($thumb, $destPath, $type, self::THUMB_QUALITY);
    }

    private function thumbPath(string $filename): string
    {
        return $this->uploadDir . '/' . self::THUMB_SUBDIR . '/' . basename($filename);
    }
}