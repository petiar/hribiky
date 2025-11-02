<?php

namespace App\Controller;

use App\Form\MushroomPublicEditType;
use App\Service\MushroomEditLinkService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

class MushroomPublicEditController extends AbstractController
{
    public function __construct(
        private MushroomEditLinkService $links,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/mushroom/edit/{token}', name: 'mushroom_public_edit')]
    public function edit(Request $request, string $token): Response
    {
        $mushroom = $this->links->validateAndGetMushroom($token);
        if (!$mushroom) {
            return $this->render('mushroom/public_edit_invalid.html.twig');
        }

        $form = $this->createForm(MushroomPublicEditType::class, $mushroom, [
            'csrf_protection' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->links->consume($token);
            return $this->render('mushroom/public_edit_done.html.twig');
        }

        return $this->render('mushroom/public_edit.html.twig', [
            'form' => $form,
            'mushroom' => $mushroom,
        ]);
    }
}
