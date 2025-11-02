<?php

namespace App\Service;

use App\Entity\Mushroom;
use App\Entity\MushroomEditLink;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MushroomEditLinkService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $router
    ) {}

    public function create(Mushroom $mushroom, \DateInterval $ttl = new \DateInterval('P7D')): ?string
    {
        if (!$mushroom->getEmail()) {
            return null; // nie je komu poslať
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash  = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable())->add($ttl);

        // zmaž starý link ak existuje (nech je vždy len jeden)
        $old = $this->em->getRepository(MushroomEditLink::class)->findOneBy(['mushroom' => $mushroom]);
        if ($old) $this->em->remove($old);

        $link = new MushroomEditLink($mushroom, $hash, $expiresAt, $mushroom->getEmail());
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
