<?php

namespace App\Twig;

use App\Service\ThumbnailService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * photo_thumb(path) — cesta k náhľadu fotky (400 px), ak existuje, inak k originálu.
 * Použitie: asset(photo_thumb(photo.path)) všade, kde je obrázok malý (kartičky, miniatúry).
 */
class PhotoExtension extends AbstractExtension
{
    public function __construct(
        private readonly ThumbnailService $thumbnailService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('photo_thumb', $this->thumbPath(...)),
        ];
    }

    public function thumbPath(string $filename): string
    {
        return ltrim($this->thumbnailService->publicUrl($filename), '/');
    }
}
