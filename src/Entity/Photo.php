<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Photo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 255)]
    private string $owner;

    #[ORM\ManyToOne(targetEntity: Mushroom::class, inversedBy: "photos")]
    private ?Mushroom $mushroom = null;

    #[ORM\ManyToOne(targetEntity: MushroomComment::class, inversedBy: "photos")]
    private ?MushroomComment $mushroomComment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Photo
    {
        $this->id = $id;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): Photo
    {
        $this->path = $path;

        return $this;
    }

    public function getMushroom(): ?Mushroom
    {
        return $this->mushroom;
    }

    public function setMushroom(?Mushroom $mushroom): Photo
    {
        $this->mushroom = $mushroom;

        return $this;
    }

    public function getMushroomComment(): ?MushroomComment
    {
        return $this->mushroomComment;
    }

    public function setMushroomComment(?MushroomComment $mushroomComment): Photo
    {
        $this->mushroomComment = $mushroomComment;

        return $this;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function setOwner(string $owner): Photo
    {
        $this->owner = $owner;

        return $this;
    }


}
