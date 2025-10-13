<?php

namespace App\Controller;

use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use App\Form\MushroomCommentType;
use App\Service\FotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MushroomCommentController extends AbstractController
{
    #[Route('/rozcestnik-update', name: 'rozcestnik_update_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, FotoUploader $fotoUploader): JsonResponse
    {
        $update = new MushroomComment();
        $form = $this->createForm(MushroomCommentType::class, $update, [
            'attr' => ['id' => 'form_rozcestnik_update'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rozcestnikId = $form->get('rozcestnik_id')->getData();
            $rozcestnik = $entityManager->getRepository(Mushroom::class)->find($rozcestnikId);

            if (!$rozcestnik) {
                return new JsonResponse(['success' => false, 'error' => 'Rozcestník neexistuje'], 404);
            }

            $uploadedFiles = $form->get('photos')->getData();
            $fotoUploader->uploadAndAttach($uploadedFiles, $update);

            $update->setMushroom($rozcestnik);
            $entityManager->persist($update);
            $entityManager->flush();

            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse([
            'success' => false,
            'errors' => (string) $form->getErrors(true, false)
        ]);
    }
}
