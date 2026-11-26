<?php

namespace App\Controller;

use App\Entity\Mushroom;
use App\Entity\Photo;
use App\Form\MushroomType;
use App\Repository\MushroomRepository;
use App\Service\FotoUploader;
use App\Service\MushroomApiFormatter;
use App\Service\MailService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validation;

#[Route('/api/mushrooms', name: 'api_mushrooms_')]
class MushroomApiController extends AbstractController
{
    #[Route('', name: '_index', methods: ['GET'])]
    public function index(Request $request, MushroomRepository $mushroomRepository, MushroomApiFormatter $formatter): JsonResponse
    {
        $sinceId = $request->query->getInt('since_id', 0);
        // Bez parametra (alebo limit=0) vráti všetky publikované hríbiky.
        // Pôvodne bol predvolený limit 100 a appka tak nevidela staršie hríbiky.
        $limit = $request->query->getInt('limit', 0);

        $mushrooms = $mushroomRepository->findPublishedForApi($sinceId, $limit > 0 ? $limit : null);

        $baseUrl = $request->getSchemeAndHttpHost();
        $data = array_map(fn(Mushroom $mushroom) => $formatter->mushroom($mushroom, $baseUrl), $mushrooms);

        return $this->json($data);
    }

    #[Route('/nearby', name: '_nearby', methods: ['GET'])]
    public function nearby(Request $request, MushroomRepository $mushroomRepository): JsonResponse
    {
        $latitude = $request->query->get('latitude');
        $longitude = $request->query->get('longitude');
        $radius = $request->query->get('radius', 100); // default 100m

        if (!$latitude || !$longitude) {
            return $this->json(['error' => 'Missing coordinates'], 400);
        }

        $nearby = $mushroomRepository->findNearby($latitude, $longitude, $radius);

        return new JsonResponse(['hribiky' => $nearby]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Mushroom $mushroom, Request $request, MushroomApiFormatter $formatter): JsonResponse
    {
        if ($mushroom->isPublished()) {
            $baseUrl = $request->getSchemeAndHttpHost();

            $comments = array_values(array_filter(
                $mushroom->getUpdates()->toArray(),
                fn($comment) => $comment->isPublished()
            ));

            return $this->json($formatter->mushroom($mushroom, $baseUrl) + [
                'comments' => array_map(fn($comment) => $formatter->comment($comment, $baseUrl), $comments),
            ]);
        }
        return $this->json([]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function add(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, FotoUploader $fotoUploader, MailService $mailService): JsonResponse
    {
        foreach (['title', 'latitude', 'longitude', 'name'] as $field) {
            if (!$request->request->has($field)) {
                return $this->json([
                    'status' => 'error',
                    'message' => sprintf('Missing required field: %s', $field),
                    'id' => null,
                ], 422);
            }
        }
        $mushroom = new Mushroom();
        $mushroom->setTitle($request->request->get('title'));
        $mushroom->setDescription($request->request->get('description'));
        $mushroom->setLatitude($request->request->get('latitude'));
        $mushroom->setLongitude($request->request->get('longitude'));
        $mushroom->setAltitude($request->request->get('altitude'));
        $mushroom->setName($request->request->get('name'));
        $mushroom->setEmail($request->request->get('email'));
        $mushroom->setCountry($request->request->get('country'));
        $mushroom->setPublished(0);
        $mushroom->setSource('api');

        $files = $request->files->get('photos') ?? [];
        if ($files) {
            $fotoUploader->uploadAndAttach($files, $mushroom);
        }
        $entityManager->persist($mushroom);
        $entityManager->flush();

        $mailService->sendMushroomAdmin($mushroom);
        if ($mushroom->getEmail()) {
            $mailService->sendMushroomThankYou($mushroom);
        }
        return new JsonResponse(['status' => 'ok', 'id' => $mushroom->getId()], 201);
    }
}

