<?php

namespace App\Service;

use App\Entity\Mushroom;
use App\Repository\MushroomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MushroomApprovalService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MushroomRepository $mushroomRepository,
        private UrlGeneratorInterface $router,
    ) {}

    public function generateApprovalUrl(Mushroom $mushroom): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $token);

        $mushroom->setApprovalToken($hash);
        $this->em->flush();

        return $this->router->generate(
            'admin_mushroom_approve',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function approveByToken(string $token): ?Mushroom
    {
        $hash = hash('sha256', $token);
        $mushroom = $this->mushroomRepository->findOneBy(['approvalToken' => $hash]);

        if (!$mushroom) {
            return null;
        }

        $mushroom->setPublished(true);
        $mushroom->setApprovalToken(null);
        $this->em->flush();

        return $mushroom;
    }
}