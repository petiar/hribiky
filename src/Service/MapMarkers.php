<?php

namespace App\Service;

use App\Entity\Mushroom;

/**
 * Dáta pre mapu (public/js/hribiky.js → premenná `hribiky`): hlavná stránka aj stránky pohorí a krajov.
 */
class MapMarkers
{
    public function __construct(private readonly ThumbnailService $thumbnailService)
    {
    }

    /**
     * @param iterable<Mushroom> $mushrooms fotky majú byť načítané fetch joinom
     */
    public function toJson(iterable $mushrooms): string
    {
        $data = [];
        foreach ($mushrooms as $mushroom) {
            $data[] = [
                'id' => $mushroom->getId(),
                'title' => $mushroom->getTitle(),
                'description' => $mushroom->getDescription(),
                'latitude' => $mushroom->getLatitude(),
                'longitude' => $mushroom->getLongitude(),
                // Náhľady tu negenerujeme (vznikajú pri nahraní a príkazom app:generate-thumbnails v deployi) —
                // inak by po zmene veľkosti náhľadov jedna návšteva mapy generovala stovky obrázkov naraz.
                'fotky' => array_map(
                    fn ($fotka) => $this->thumbnailService->publicUrl($fotka->getPath()),
                    $mushroom->getPhotos()->toArray(),
                ),
            ];
        }

        // Ide do <script> — HEX_TAG, aby text od používateľa (</script>) nemohol ukončiť skript.
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
    }
}
