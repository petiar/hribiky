<?php

namespace App\Controller;

use App\Form\MushroomCommentPublicEditType;
use App\Service\MushroomCommentEditLinkService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MushroomCommentPublicEditController extends AbstractController
{
    public function __construct(
        private MushroomCommentEditLinkService $links,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/comment/edit/{token}', name: 'mushroom_comment_public_edit')]
    public function edit(Request $request, string $token): Response
    {
        $comment = $this->links->validateAndGetComment($token);
        if (!$comment) {
            return $this->render('mushroom_comment/public_edit_invalid.html.twig');
        }

        $form = $this->createForm(MushroomCommentPublicEditType::class, $comment, [
            'csrf_protection' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->links->consume($token);
            return $this->render('mushroom_comment/public_edit_done.html.twig');
        }

        return $this->render('mushroom_comment/public_edit.html.twig', [
            'form' => $form,
            'comment' => $comment,
        ]);
    }
}