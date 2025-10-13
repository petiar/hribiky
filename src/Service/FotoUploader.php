<?php

namespace App\Service;

use App\Entity\Photo;
use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use Doctrine\ORM\EntityManagerInterface;

class FotoUploader {
    public function __construct(private string $uploadDir, private EntityManagerInterface $entityManager) {}

    public function uploadAndAttach(array $uploadedFiles, object $owner): void
    {
        foreach ($uploadedFiles as $file) {
            $newFilename = uniqid().'.'.$file->guessExtension();
            $file->move($this->uploadDir, $newFilename);

            $foto = new Photo();
            $foto->setPath($newFilename);
            $foto->setOwner($owner::class);
            if ($owner instanceof Mushroom ) {
                $foto->setMushroom($owner);
            }
            if ($owner instanceof MushroomComment ) {
                $foto->setMushroomComment($owner);
            }
            $this->entityManager->persist($foto);
        }
    }
}
