<?php

 namespace App\Controller;

 use App\Entity\Mushroom;
 use App\Entity\MushroomComment;
 use App\Repository\MushroomCommentRepository;
 use App\Service\FotoUploader;
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
     public function index(EntityManagerInterface $em, Request $request, MushroomCommentRepository $mushroomCommentRepository): JsonResponse
     {
         $sinceId = $request->query->getInt('since_id', 0);
         $limit = $request->query->getInt('limit', 100);
         $mushroomId = $request->query->getInt('mushroom_id', 0);

         $query = $mushroomCommentRepository->createQueryBuilder('m');
         if ($sinceId) {
             $query->where('m.id > :sinceId')
                 ->setParameter('sinceId', $sinceId);
         }
         if ($mushroomId) {
             $query->andWhere('m.mushroom = :mushroomId')
                 ->setParameter('mushroomId', $mushroomId);
         }
         $query->andWhere('m.published = 1')
             ->orderBy('m.id', 'DESC')
             ->setMaxResults($limit);

         $mushroomsComments = $query->getQuery()->getResult();

         $baseUrl = $request->getSchemeAndHttpHost();

         $data = [];
         foreach ($mushroomsComments as $comment) {
             $photos = $comment->getPhotos()->toArray();
             $photosUrls = array_map(function ($photo) use ($baseUrl) {
                 return $baseUrl . '/uploads/photos/' . $photo->getPath();
             }, $photos);
             $data[] = [
                 'id' => $comment->getId(),
                 'name' => $comment->getName(),
                 'description' => $comment->getDescription(),
                 'photos' => $photosUrls,
             ];
         }

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
