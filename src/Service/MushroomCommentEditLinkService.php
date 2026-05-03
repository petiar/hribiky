<?php

namespace App\Service;

use App\Entity\MushroomComment;
use App\Entity\MushroomCommentEditLink;
use App\Repository\MushroomCommentEditLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MushroomCommentEditLinkService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $router,
        private MushroomCommentEditLinkRepository $repository,
        private LoggerInterface $logger,
    ) {}

    public function create(MushroomComment $comment, \DateInterval $ttl = new \DateInterval('P7D')): ?string
    {
        if (!$comment->getEmail()) {
            return null;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable())->add($ttl);

        $deleted = $this->repository->deleteOldUsedLinks();
        $this->logger->info(sprintf('Zmazal som %d starých comment edit link záznamov.', $deleted));

        $link = (new MushroomCommentEditLink())
            ->setTokenHash($hash)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setExpiresAt($expiresAt)
            ->setMushroomComment($comment);

        $this->em->persist($link);
        $this->em->flush();

        return $this->router->generate('mushroom_comment_public_edit', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function validateAndGetComment(string $token): ?MushroomComment
    {
        $hash = hash('sha256', $token);
        /** @var MushroomCommentEditLink|null $link */
        $link = $this->em->getRepository(MushroomCommentEditLink::class)->findOneBy(['tokenHash' => $hash]);
        if (!$link || !$link->isUsable()) {
            return null;
        }
        return $link->getMushroomComment();
    }

    public function consume(string $token): void
    {
        $hash = hash('sha256', $token);
        /** @var MushroomCommentEditLink|null $link */
        $link = $this->em->getRepository(MushroomCommentEditLink::class)->findOneBy(['tokenHash' => $hash]);
        if ($link && $link->isUsable()) {
            $link->markUsed();
            $this->em->flush();
        }
    }
}