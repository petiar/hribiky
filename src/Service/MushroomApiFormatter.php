<?php

namespace App\Service;

use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use App\Entity\Photo;

/**
 * Jednotný tvar hríbika a komentára v API pre mobilnú aplikáciu
 * (/api/mushrooms, /api/mushrooms/{id}, /api/search).
 *
 * Spätná kompatibilita: polia sa smú len pridávať, nie meniť ani odoberať (viď docs/api/README.md).
 * Nikdy sem nepridávaj e-mail ani iné neverejné údaje.
 */
class MushroomApiFormatter
{
    public function __construct(private ThumbnailService $thumbnailService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function mushroom(Mushroom $mushroom, string $baseUrl): array
    {
        return [
            'id' => $mushroom->getId(),
            'title' => $mushroom->getTitle(),
            'name' => $mushroom->getName(),
            'description' => $mushroom->getDescription(),
            'latitude' => $mushroom->getLatitude(),
            'longitude' => $mushroom->getLongitude(),
            'altitude' => $mushroom->getAltitude(),
            'photos' => $this->photoUrls($mushroom->getPhotos()->toArray(), $baseUrl),
            'photo_thumbnails' => $this->thumbnailUrls($mushroom->getPhotos()->toArray(), $baseUrl),
            'createdAt' => $mushroom->getCreatedAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function comment(MushroomComment $comment, string $baseUrl): array
    {
        return [
            'id' => $comment->getId(),
            'name' => $comment->getName(),
            'description' => $comment->getDescription(),
            'photos' => $this->photoUrls($comment->getPhotos()->toArray(), $baseUrl),
            'photo_thumbnails' => $this->thumbnailUrls($comment->getPhotos()->toArray(), $baseUrl),
            'createdAt' => $comment->getCreatedAt(),
        ];
    }

    /**
     * @param Photo[] $photos
     * @return string[]
     */
    private function photoUrls(array $photos, string $baseUrl): array
    {
        return array_values(array_map(
            fn(Photo $photo) => $baseUrl . '/uploads/photos/' . $photo->getPath(),
            $photos
        ));
    }

    /**
     * Náhľady (800 px) v rovnakom poradí ako `photos` — photo_thumbnails[i] je náhľad photos[i].
     * Ak náhľad ešte neexistuje, je tu URL originálu (pole má vždy rovnakú dĺžku ako `photos`).
     *
     * @param Photo[] $photos
     * @return string[]
     */
    private function thumbnailUrls(array $photos, string $baseUrl): array
    {
        return array_values(array_map(
            fn(Photo $photo) => $baseUrl . $this->thumbnailService->publicUrl($photo->getPath()),
            $photos
        ));
    }
}
