<?php

namespace App\EventListener;

use App\Entity\AccessLog;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\ORM\EntityManagerInterface;

class EntityCreateLogger
{
    public function __construct(
        private RequestStack $requestStack,
        private EntityManagerInterface $em
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof AccessLog) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) return;

        $ip = $request->getClientIp();
        $path = $request->getPathInfo();
        $data = json_encode($request->request->all(), true);
        $userAgent = $request->headers->get('User-Agent');

        $entityClass = get_class($entity);
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;

        $log = new AccessLog($ip, $entityClass, $entityId, $path, $userAgent, $data);

        $this->em->persist($log);
        $this->em->flush();
    }
}
