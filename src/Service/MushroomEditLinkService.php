<?php

namespace App\Service;

use App\Entity\Mushroom;
use App\Entity\MushroomEditLink;
use App\Repository\MushroomEditLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MushroomEditLinkService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $router,
        private readonly MushroomEditLinkRepository $mushroomEditLinkRepository,
        private LoggerInterface $logger
    ) {}

    public function create(Mushroom $mushroom, \DateInterval $ttl = new \DateInterval('P7D')): ?string
    {
        if (!$mushroom->getEmail()) {
            return null;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash  = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable())->add($ttl);

        $deleted = $this->mushroomEditLinkRepository->deleteOldUsedLinks();
        $this->logger->info(sprintf('Zmazal som %d starých záznamov.', $deleted));

        $link = new MushroomEditLink();
        $link->setTokenHash($hash)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setExpiresAt($expiresAt)
            ->setMushroom($mushroom);

        $this->em->persist($link);
        $this->em->flush();

        $url = $this->router->generate('mushroom_public_edit', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        return $url;
    }

    public function validateAndGetMushroom(string $token): ?Mushroom
    {
        $hash = hash('sha256', $token);
        $repo = $this->em->getRepository(MushroomEditLink::class);
        /** @var MushroomEditLink|null $link */
        $link = $repo->findOneBy(['tokenHash' => $hash]);
        if (!$link || !$link->isUsable()) {
            return null;
        }
        return $link->getMushroom();
    }

    public function consume(string $token): void
    {
        $hash = hash('sha256', $token);
        $repo = $this->em->getRepository(MushroomEditLink::class);
        /** @var MushroomEditLink|null $link */
        $link = $repo->findOneBy(['tokenHash' => $hash]);
        if ($link && $link->isUsable()) {
            $link->markUsed();
            $this->em->flush();
        }
    }
}
