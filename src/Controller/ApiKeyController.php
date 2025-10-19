<?php

// src/Controller/ApiKeyWebController.php
namespace App\Controller;

use App\Entity\ApiKey;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ApiKeyController extends AbstractController
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/generate-key', name: 'generate_api_key')]
    public function generate(
        Request $request,
        EntityManagerInterface $entityManager,
        MailService $mailService
    ) {
        $apiKey = null;
        $error = null;

        if ($request->isMethod('POST')) {
            $emailInput = $request->request->get('email');

            if (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
                $error = 'Zadajte platný e-mail.';
            } else {
                $apiKey = $entityManager->getRepository(ApiKey::class)
                    ->findOneBy(['email' => $emailInput]);

                $newApiKey = bin2hex(random_bytes(16));
                $validUntil = (new \DateTimeImmutable())->modify('+1 year');

                if ($apiKey) {
                    $apiKey->setKeyValue($newApiKey);
                    $apiKey->setValidUntil($validUntil);
                } else {
                    $apiKey = new ApiKey($emailInput, $newApiKey);
                    $entityManager->persist($apiKey);
                }

                $entityManager->flush();

                $mailService->send(
                    'api/api_key_mail.html.twig',
                    'Tvoj nový API kľúč',
                    $emailInput,
                    [
                        'key' => $apiKey,
                    ]
                );
            }
        }

        return $this->render('api/generate.html.twig', [
            'apiKey' => $apiKey,
            'error' => $error,
        ]);
    }
}

