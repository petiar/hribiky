<?php

namespace App\Controller;

use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use App\Form\MushroomType;
use App\Form\MushroomCommentType;
use App\Repository\MushroomRepository;
use App\Service\FotoUploader;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;

class MushroomController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $mushrooms = $entityManager->getRepository(Mushroom::class)->findBy(['published' => 1]);
        $data = [];
        foreach ($mushrooms as $mushroom) {
            $data[] = [
                'id' => $mushroom->getId(),
                'title' => $mushroom->getTitle(),
                'description' => $mushroom->getDescription(),
                'latitude' => $mushroom->getLatitude(),
                'longitude' => $mushroom->getLongitude(),
                'fotky' => array_map(fn ($fotka) => '/uploads/photos/' . basename($fotka->getPath()), $mushroom->getPhotos()->toArray()),
            ];
        }
        $mushroomsJson = $serializer->serialize($data, 'json');
        $mushroom = new Mushroom();
        $mushroomForm = $this->createForm(MushroomType::class, $mushroom);
        $mushroomComment = new MushroomComment();
        $mushroomCommentForm = $this->createForm(MushroomCommentType::class, $mushroomComment);

        return $this->render('map/index.html.twig', [
            'mushrooms' => $mushroomsJson,
            'form' => $mushroomForm->createView(),
            'rozcestnikUpdateForm' => $mushroomCommentForm->createView(),
            'count' => count($mushrooms),
            'randomRozcestnik' => $mushrooms[rand(0, count($mushrooms) - 1)],
        ]);
    }

    #[Route('/api/nearby', name: 'api_nearby', methods: ['GET'])]
    public function nearby(Request $request, MushroomRepository $mushroomRepository): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $radius = $request->query->get('radius', 100); // default 100m

        if (!$lat || !$lng) {
            return $this->json(['error' => 'Missing coordinates'], 400);
        }

        $nearby = $mushroomRepository->findNearby($lat, $lng, $radius);

        return new JsonResponse(['hribiky' => $nearby]);
    }

    #[Route('/rozcestnik/create', name: 'rozcestnik_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, FotoUploader $fotoUploader, MailService $mailService ): JsonResponse
    {
        $mushroom = new Mushroom();
        $form = $this->createForm(MushroomType::class, $mushroom);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFiles = $form->get('photos')->getData();
            $fotoUploader->uploadAndAttach($uploadedFiles, $mushroom);

            $mushroom->setSource('web');

            $entityManager->persist($mushroom);
            $entityManager->flush();

            $mailService->sendMushroomAdmin($mushroom);
            if ($mushroom->getEmail()) {
                $mailService->sendMushroomThankYou($mushroom);
            }
            return $this->json([
                'success' => true,
                'message' => 'Rozcestník bol úspešne pridaný!',
                'rozcestnikId' => $mushroom->getId()
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return $this->json([
            'success' => false,
            'errors' => $errors,
        ], 400);
    }

    #[Route('/{id}', name: 'mushroom_detail', requirements: ['id' => '\d+'])]
    public function detail(int $id, EntityManagerInterface $em): Response
    {
        $mushroom = $em->getRepository(Mushroom::class)->find($id);
        if (!$mushroom) {
            throw $this->createNotFoundException('Rozcestník nenájdený');
        }

        return $this->render('map/detail.html.twig', [
            'mushroom' => $mushroom,
        ]);
    }
}
