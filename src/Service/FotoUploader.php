<?php

namespace App\Service;

use App\Entity\BlogPost;
use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use App\Entity\Photo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FotoUploader
{
    /**
     * Najväčšia povolená fotka vo webových formulároch (pred zmenšením).
     * Web fotky zmenšuje už v prehliadači (~1 MB); toto je poistka, ak zmenšenie zlyhá.
     * PHP na serveri povoľuje 256 MB (upload_max_filesize).
     */
    public const MAX_FILE_SIZE = '20M';

    /** Najviac fotiek k jednému hríbiku alebo komentáru (web aj API) — rovnako ako v mobilnej appke. */
    public const MAX_FILES = 5;

    /**
     * Zoznam nahraných súborov z ľubovoľnej kombinácie polí (photo, photo[], photos, photos[]).
     *
     * @return UploadedFile[]
     */
    public static function flattenFiles(mixed ...$values): array
    {
        $files = [];
        array_walk_recursive($values, function ($value) use (&$files) {
            if ($value instanceof UploadedFile) {
                $files[] = $value;
            }
        });

        return $files;
    }

    public function __construct(
        private string $uploadDir,
        private EntityManagerInterface $entityManager,
        private ThumbnailService $thumbnailService,
        private ImageOptimizer $imageOptimizer,
    ) {}

    /**
     * Uloží nahraný súbor: presun do uploads, zmenšenie a odstránenie EXIF, náhľad.
     * Vráti názov uloženého súboru.
     */
    public function store(UploadedFile $file): string
    {
        $newFilename = uniqid() . '.' . $file->guessExtension();
        $file->move($this->uploadDir, $newFilename);

        $this->imageOptimizer->optimize($this->uploadDir . '/' . $newFilename);
        $this->thumbnailService->generate($newFilename);

        return $newFilename;
    }

    public function uploadAndAttach(array $uploadedFiles, object $owner): void
    {
        // Všetky súbory, aj vnorené (photo[]) — predtým sa z poľa uložil len prvý.
        foreach (self::flattenFiles($uploadedFiles) as $file) {
            $newFilename = $this->store($file);

            $foto = new Photo();
            $foto->setPath($newFilename);
            $foto->setOwner($owner::class);
            if ($owner instanceof Mushroom) {
                $foto->setMushroom($owner);
                $owner->addFotka($foto);
            }
            if ($owner instanceof MushroomComment) {
                $foto->setMushroomComment($owner);
                $owner->addFotka($foto);
            }
            if ($owner instanceof BlogPost) {
                $foto->setBlogPost($owner);
            }
            $this->entityManager->persist($foto);
        }
    }
}
