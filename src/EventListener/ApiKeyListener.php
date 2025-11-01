<?php

// src/EventListener/ApiKeyListener.php
namespace App\EventListener;

use App\Entity\ApiKey;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiKeyListener
{

    private string $iosApiKey;

    public function __construct(
        private EntityManagerInterface $em,
        string $iosApiKey
    ) {
        $this->iosApiKey = $iosApiKey;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/mushrooms')) {
            return;
        }

        $providedKey = $request->headers->get('Api-Key');

        if (!$providedKey) {
            $event->setResponse(
                new JsonResponse(['error' => 'Missing API key'], 401)
            );
            return;
        }

        if ($providedKey === $this->iosApiKey) {
            return;
        }

        $now = new \DateTimeImmutable();

        $key = $this->em->getRepository(ApiKey::class)
            ->createQueryBuilder('m')
            ->where('m.keyValue = :key')
            ->andWhere('m.validUntil > :now')
            ->setParameter('key', $providedKey)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$key) {
            $event->setResponse(
                new JsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid API key',
                    'id' => null,
                    ], 403)
            );
        }
    }
}
