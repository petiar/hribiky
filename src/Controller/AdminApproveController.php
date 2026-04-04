<?php

namespace App\Controller;

use App\Service\MushroomApprovalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminApproveController extends AbstractController
{
    #[Route('/mushroom/approve/{token}', name: 'admin_mushroom_approve')]
    public function approve(string $token, MushroomApprovalService $approvalService): Response
    {
        $mushroom = $approvalService->approveByToken($token);

        if (!$mushroom) {
            throw $this->createNotFoundException('Odkaz je neplatný alebo už bol použitý.');
        }
        $this->addFlash('success', 'Hríbik bol schválený, si super!');

        return $this->redirectToRoute('mushroom_detail', ['id' => $mushroom->getId()]);
    }
}
