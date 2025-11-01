<?php

namespace App\Controller;

use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use App\Form\MushroomCommentType;
use App\Service\FotoUploader;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MushroomCommentController extends AbstractController
{
    #[Route('/rozcestnik-update', name: 'rozcestnik_update_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, FotoUploader $fotoUploader, MailService $mailService): JsonResponse
    {
        $mushroomComment = new MushroomComment();
        $form = $this->createForm(MushroomCommentType::class, $mushroomComment, [
            'attr' => ['id' => 'form_rozcestnik_update'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mushroomId = $form->get('rozcestnik_id')->getData();
            $mushroom = $entityManager->getRepository(Mushroom::class)->find($mushroomId);

            if (!$mushroom) {
                return new JsonResponse(['success' => false, 'error' => 'Hríbik neexistuje'], 404);
            }

            $uploadedFiles = $form->get('photos')->getData();
            $fotoUploader->uploadAndAttach($uploadedFiles, $mushroomComment);

            $mushroomComment->setMushroom($mushroom);
            $mushroomComment->setSource('web');
            $entityManager->persist($mushroomComment);
            $entityManager->flush();

            $mailService->sendMushroomCommentAdmin($mushroomComment);
            if ($mushroom->getEmail()) {
                $mailService->sendMushroomCommentThankYou($mushroomComment);
            }

            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse([
            'success' => false,
            'errors' => (string) $form->getErrors(true, false)
        ]);
    }
}
