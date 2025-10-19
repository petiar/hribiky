<?php

namespace App\Controller;

use App\Entity\Mushroom;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mushrooms')]
class MushroomApiController extends AbstractController
{

    #[Route('', name: 'api_mushrooms_list', methods: ['GET'])]
    public function list(EntityManagerInterface $em, Request $request): JsonResponse
    {
        $mushrooms = $em->getRepository(Mushroom::class)->findAll();
        $baseUrl = $request->getSchemeAndHttpHost();

        $data = [];
        foreach ($mushrooms as $mushroom) {
            $photos = $mushroom->getPhotos()->toArray();
            $photosUrls = array_map(function ($photo) use ($baseUrl) {
                return $baseUrl . '/uploads/photos/' . $photo->getPath();
            }, $photos);
            $data[] = [
                'id' => $mushroom->getId(),
                'title' => $mushroom->getTitle(),
                'name' => $mushroom->getName(),
                'description' => $mushroom->getDescription(),
                'latitude' => $mushroom->getLatitude(),
                'longitude' => $mushroom->getLongitude(),
                'altitude' => $mushroom->getAltitude(),
                'photos' => $photosUrls,
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_mushroom_detail', methods: ['GET'])]
    public function detail(Mushroom $mushroom): JsonResponse
    {
        return $this->json([
            'id' => $mushroom->getId(),
            'name' => $mushroom->getName(),
            'description' => $mushroom->getDescription(),
            'latitude' => $mushroom->getLatitude(),
            'longitude' => $mushroom->getLongitude(),
            'altitude' => $mushroom->getAltitude(),
        ]);
    }
}

