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
}
