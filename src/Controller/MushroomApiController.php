<?php

namespace App\Controller;

use App\Entity\Mushroom;
use App\Entity\Photo;
use App\Form\MushroomType;
use App\Repository\MushroomRepository;
use App\Service\ApiInput;
use App\Service\FotoUploader;
use App\Service\MushroomApiFormatter;
use App\Service\TitleNormalizer;
use App\Service\UploadLimits;
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
        // Kľúč `error` je pôvodný formát chyby tohto endpointu (appka ho môže čítať), `status` pre jednotnosť.
        if (!$request->query->has('latitude') || !$request->query->has('longitude')) {
            return $this->json(['status' => 'error', 'error' => 'Missing coordinates'], 400);
        }

        $latitude = ApiInput::latitude($request->query->get('latitude'));
        $longitude = ApiInput::longitude($request->query->get('longitude'));
        $radius = ApiInput::number($request->query->get('radius', 100), 0.001); // predvolene 100 m

        if ($latitude === null || $longitude === null) {
            return $this->json(['status' => 'error', 'error' => 'Invalid coordinates'], 400);
        }
        if ($radius === null) {
            return $this->json(['status' => 'error', 'error' => 'Invalid radius'], 400);
        }

        $nearby = $mushroomRepository->findNearby($latitude, $longitude, $radius);

        return new JsonResponse(['hribiky' => $nearby]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
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
    public function add(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, FotoUploader $fotoUploader, MailService $mailService, UploadLimits $uploadLimits): JsonResponse
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

        // photos[] aj photos (jeden súbor)
        $files = FotoUploader::flattenFiles($request->files->get('photos'));

        if (count($files) > FotoUploader::MAX_FILES) {
            return $this->json([
                'status' => 'error',
                'code' => 'too_many_photos',
                'message' => sprintf('Too many photos (max %d).', FotoUploader::MAX_FILES),
                'id' => null,
            ], 422);
        }

        // Priveľká alebo neúplne nahraná fotka — predtým tu server spadol s 500 a hríbik sa neuložil.
        if ($invalid = $uploadLimits->findInvalidFile($files)) {
            $error = $uploadLimits->apiError($invalid);

            return $this->json(['status' => 'error'] + $error + ['id' => null], $error['code'] === 'photo_too_large' ? 413 : 400);
        }

        // Len skutočné fotky (podľa obsahu súboru) — nie HTML, SVG, PHP…
        if ($unsupported = $uploadLimits->findUnsupportedFile($files)) {
            return $this->json(['status' => 'error'] + $uploadLimits->unsupportedApiError($unsupported) + ['id' => null], 415);
        }
        // Súradnice a výška musia byť čísla v rozsahu — inak setter entity spadol na TypeError (500).
        $latitude = ApiInput::latitude($request->request->get('latitude'));
        $longitude = ApiInput::longitude($request->request->get('longitude'));
        $rawAltitude = trim((string) $request->request->get('altitude', ''));
        $altitude = $rawAltitude === '' ? null : ApiInput::number($rawAltitude, -500, 9000);

        foreach (['latitude' => [$latitude, -90, 90], 'longitude' => [$longitude, -180, 180]] as $field => [$value, $min, $max]) {
            if ($value === null) {
                return $this->invalidField($field, sprintf('a number between %d and %d', $min, $max));
            }
        }
        if ($rawAltitude !== '' && $altitude === null) {
            return $this->invalidField('altitude', 'a number in meters');
        }

        $mushroom = new Mushroom();
        // NÁZOV CELÝ VEĽKÝMI PÍSMENAMI → Názov S Veľkými Prvými Písmenami
        $mushroom->setTitle(TitleNormalizer::fixAllCaps((string) $request->request->get('title')));
        $mushroom->setDescription($request->request->get('description'));
        $mushroom->setLatitude($latitude);
        $mushroom->setLongitude($longitude);
        $mushroom->setAltitude($altitude);
        $mushroom->setName($request->request->get('name'));
        $mushroom->setEmail($request->request->get('email'));
        $mushroom->setCountry($request->request->get('country'));
        $mushroom->setPublished(0);
        $mushroom->setSource('api');

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

    private function invalidField(string $field, string $expected): JsonResponse
    {
        return $this->json([
            'status' => 'error',
            'code' => 'invalid_field',
            'message' => sprintf('Invalid field: %s (must be %s).', $field, $expected),
            'field' => $field,
            'id' => null,
        ], 422);
    }
}
