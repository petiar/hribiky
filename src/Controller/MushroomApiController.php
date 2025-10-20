<?php

namespace App\Controller;

use App\Entity\Mushroom;
use App\Entity\Photo;
use App\Form\MushroomType;
use App\Repository\MushroomRepository;
use App\Service\FotoUploader;
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
    public function index(EntityManagerInterface $em, Request $request, MushroomRepository $mushroomRepository): JsonResponse
    {
        $sinceId = $request->query->getInt('since_id', 0);
        $limit = $request->query->getInt('limit', 100);

        $mushrooms = $sinceId > 0
            ? $mushroomRepository->createQueryBuilder('m')
                ->where('m.id > :since')
                ->setParameter('since', $sinceId)
                ->andWhere('m.published = 1')
                ->orderBy('m.id', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()->getResult()
            : $mushroomRepository->findBy(['published' => 1], ['id' => 'DESC'], $limit);

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

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Mushroom $mushroom): JsonResponse
    {
        if ($mushroom->isPublished()) {
            return $this->json([
                'id' => $mushroom->getId(),
                'name' => $mushroom->getName(),
                'description' => $mushroom->getDescription(),
                'latitude' => $mushroom->getLatitude(),
                'longitude' => $mushroom->getLongitude(),
                'altitude' => $mushroom->getAltitude(),
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
                ]);
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
        $mushroom->setPublished(0);

        if ($file = $request->files->get('photo')) {
            $fotoUploader->uploadAndAttach([$file], $mushroom);
        }
        $entityManager->persist($mushroom);
        $entityManager->flush();

        $mailService->sendMushroomAdmin($mushroom);
        $mailService->sendMushroomThankYou($mushroom);

        return $this->json(['status' => 'ok', 'id' => $mushroom->getId()]);
    }
}

