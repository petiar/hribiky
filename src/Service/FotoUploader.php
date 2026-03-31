<?php

namespace App\Service;

use App\Entity\BlogPost;
use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use App\Entity\Photo;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ThumbnailService;

class FotoUploader {
    public function __construct(
        private string $uploadDir,
        private EntityManagerInterface $entityManager,
        private ThumbnailService $thumbnailService,
    ) {}

    public function uploadAndAttach(array $uploadedFiles, object $owner): void
    {
        foreach ($uploadedFiles as $file) {
            $newFilename = uniqid().'.'.$file->guessExtension();
            $file->move($this->uploadDir, $newFilename);
            $this->thumbnailService->generate($newFilename);

            $foto = new Photo();
            $foto->setPath($newFilename);
            $foto->setOwner($owner::class);
            if ($owner instanceof Mushroom ) {
                $foto->setMushroom($owner);
            }
            if ($owner instanceof MushroomComment) {
                $foto->setMushroomComment($owner);
            }
            if ($owner instanceof BlogPost) {
                $foto->setBlogPost($owner);
            }
            $this->entityManager->persist($foto);
        }
    }
}
