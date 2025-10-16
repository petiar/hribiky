<?php

namespace App\EventListener;

use App\Entity\Blacklist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Response;

class BlacklistRequestListener
{
    public function __construct(private EntityManagerInterface $em) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $ip = $request->getClientIp();

        if (!$ip) return;

        $blocked = $this->em->getRepository(Blacklist::class)->findOneBy(['ipAddress' => $ip]);
        if ($blocked) {
            $response = new Response('Access forbidden.', Response::HTTP_FORBIDDEN);
            $event->setResponse($response);
        }
    }
}
