<?php

 namespace App\Controller;

 use App\Entity\Mushroom;
 use App\Entity\MushroomComment;
 use App\Repository\MushroomCommentRepository;
 use App\Service\FotoUploader;
 use App\Service\MushroomApiFormatter;
 use App\Service\MailService;
 use Doctrine\ORM\EntityManagerInterface;
 use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
 use Symfony\Component\HttpFoundation\JsonResponse;
 use Symfony\Component\HttpFoundation\Request;
 use Symfony\Component\Routing\Attribute\Route;
 use Symfony\Component\String\Slugger\SluggerInterface;

 #[Route('/api/mushrooms_comments', name: 'api_mushrooms_comments')]
 class MushroomCommentApiController extends AbstractController
 {
     #[Route('', name: '_index', methods: ['GET'])]
     public function index(Request $request, MushroomCommentRepository $mushroomCommentRepository, MushroomApiFormatter $formatter): JsonResponse
     {
         $sinceId = $request->query->getInt('since_id', 0);
         // Bez parametra (alebo limit=0) vráti všetky publikované komentáre.
         // Pôvodne bol predvolený limit 100 a appka tak nevidela staršie komentáre.
         $limit = $request->query->getInt('limit', 0);
         $mushroomId = $request->query->getInt('mushroom_id', 0);

         $comments = $mushroomCommentRepository->findPublishedForApi($sinceId, $mushroomId, $limit > 0 ? $limit : null);

         $baseUrl = $request->getSchemeAndHttpHost();
         $data = array_map(fn(MushroomComment $comment) => $formatter->comment($comment, $baseUrl), $comments);

         return $this->json($data);
     }

     #[Route('/{id}', name: 'show', methods: ['GET'])]
     public function show(MushroomComment $mushroomComment): JsonResponse
     {
         if ($mushroomComment->isPublished()) {
             return $this->json([
                 'id' => $mushroomComment->getId(),
                 'name' => $mushroomComment->getName(),
                 'description' => $mushroomComment->getDescription(),
             ]);
         }
         return $this->json([]);
     }

     #[Route('', name: 'create', methods: ['POST'])]
     public function add(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, FotoUploader $fotoUploader, MailService $mailService): JsonResponse
     {
         foreach (['name', 'mushroom_id'] as $field) {
             if (!$request->request->has($field)) {
                 return $this->json([
                     'status' => 'error',
                     'message' => sprintf('Missing required field: %s', $field),
                     'id' => null,
                 ]);
             }
         }

         $mushroom = $entityManager->getRepository(Mushroom::class)->findOneBy(['id' => $request->get('mushroom_id')]);

         $mushroomComment = new MushroomComment();
         $mushroomComment->setDescription($request->request->get('description'));
         $mushroomComment->setName($request->request->get('name'));
         $mushroomComment->setEmail($request->request->get('email'));
         $mushroomComment->setPublished(0);
         $mushroomComment->setMushroom($mushroom);
         $mushroomComment->setSource('api');

         if ($file = $request->files->get('photo')) {
             $fotoUploader->uploadAndAttach([$file], $mushroomComment);
         }
         $entityManager->persist($mushroomComment);
         $entityManager->flush();

         $mailService->sendMushroomCommentAdmin($mushroomComment);
         if ($mushroomComment->getEmail()) {
             $mailService->sendMushroomCommentThankYou($mushroomComment);
         }
         return new JsonResponse(['status' => 'ok', 'id' => $mushroomComment->getId()], 201);
     }
 }
