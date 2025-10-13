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
    public function index(EntityManagerInterface $em, SerializerInterface $serializer): Response
    {
        $rozcestniky = $em->getRepository(Mushroom::class)->findBy(['published' => 1]);
        $data = [];
        foreach ($rozcestniky as $rozcestnik) {
            $data[] = [
                'id' => $rozcestnik->getId(),
                'title' => $rozcestnik->getTitle(),
                'description' => $rozcestnik->getDescription(),
                'latitude' => $rozcestnik->getLatitude(),
                'longitude' => $rozcestnik->getLongitude(),
                'fotky' => array_map(fn ($fotka) => '/uploads/photos/' . basename($fotka->getPath()), $rozcestnik->getPhotos()->toArray()),
            ];
        }
        $jsonRozcestniky = $serializer->serialize($data, 'json');
        $rozcestnik = new Mushroom();
        $form = $this->createForm(MushroomType::class, $rozcestnik);
        $rozcestnikUpdate = new MushroomComment();
        $rozcestnikUpdateForm = $this->createForm(MushroomCommentType::class, $rozcestnikUpdate);

        return $this->render('map/index.html.twig', [
            'hribiky' => $jsonRozcestniky,
            'form' => $form->createView(),
            'rozcestnikUpdateForm' => $rozcestnikUpdateForm->createView(),
            'count' => count($rozcestniky),
            'randomRozcestnik' => $rozcestniky[rand(0, count($rozcestniky) - 1)],
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
        $rozcestnik = new Mushroom();
        $form = $this->createForm(MushroomType::class, $rozcestnik);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFiles = $form->get('photos')->getData();
            $fotoUploader->uploadAndAttach($uploadedFiles, $rozcestnik);

            $entityManager->persist($rozcestnik);
            $entityManager->flush();

            $subject = sprintf('Nový hríbik (%s) na Hríbiky.sk!', $rozcestnik->getTitle());
            $mailService->send('emails/new_mushroom.html.twig', $subject, 'petiar@gmail.com', [
                'rozcestnik' => $rozcestnik,
            ]);

            if ($rozcestnik->getEmail()) {
                $validator = Validation::createValidator();
                $violations = $validator->validate($rozcestnik->getEmail(), new \Symfony\Component\Validator\Constraints\Email());
                if (count($violations) === 0) {
                    $subject = 'Poďakovanie z Hríbiky.sk';
                    $mailService->send('emails/thank_you.html.twig', $subject, $rozcestnik->getEmail(), [
                        'rozcestnik' => $rozcestnik,
                    ]);
                }
            }

            return $this->json([
                'success' => true,
                'message' => 'Rozcestník bol úspešne pridaný!',
                'rozcestnikId' => $rozcestnik->getId()
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

    #[Route('/{id}', name: 'rozcestnik_detail', requirements: ['id' => '\d+'])]
    public function detail(int $id, EntityManagerInterface $em): Response
    {
        $rozcestnik = $em->getRepository(Mushroom::class)->find($id);
        if (!$rozcestnik) {
            throw $this->createNotFoundException('Rozcestník nenájdený');
        }

        return $this->render('map/detail.html.twig', [
            'rozcestnik' => $rozcestnik,
        ]);
    }
}
