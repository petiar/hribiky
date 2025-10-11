<?php

namespace App\Service;

use App\Entity\Fotka;
use App\Entity\Rozcestnik;
use App\Entity\RozcestnikUpdate;
use Doctrine\ORM\EntityManagerInterface;

class FotoUploader {
    public function __construct(private string $uploadDir, private EntityManagerInterface $entityManager) {}

    public function uploadAndAttach(array $uploadedFiles, object $owner): void
    {
        foreach ($uploadedFiles as $file) {
            $newFilename = uniqid().'.'.$file->guessExtension();
            $file->move($this->uploadDir, $newFilename);

            $foto = new Fotka();
            $foto->setPath($newFilename);
            $foto->setOwner($owner::class);
            if ($owner instanceof Rozcestnik ) {
                $foto->setRozcestnik($owner);
            }
            if ($owner instanceof RozcestnikUpdate ) {
                $foto->setRozcestnikUpdate($owner);
            }
            $this->entityManager->persist($foto);
        }
    }
}
