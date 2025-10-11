<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Fotka
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 255)]
    private string $owner;

    #[ORM\ManyToOne(targetEntity: Rozcestnik::class, inversedBy: "fotky")]
    private ?Rozcestnik $rozcestnik = null;

    #[ORM\ManyToOne(targetEntity: RozcestnikUpdate::class, inversedBy: "fotky")]
    private ?RozcestnikUpdate $rozcestnikUpdate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Fotka
    {
        $this->id = $id;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): Fotka
    {
        $this->path = $path;

        return $this;
    }

    public function getRozcestnik(): ?Rozcestnik
    {
        return $this->rozcestnik;
    }

    public function setRozcestnik(?Rozcestnik $rozcestnik): Fotka
    {
        $this->rozcestnik = $rozcestnik;

        return $this;
    }

    public function getRozcestnikUpdate(): ?RozcestnikUpdate
    {
        return $this->rozcestnikUpdate;
    }

    public function setRozcestnikUpdate(?RozcestnikUpdate $rozcestnikUpdate): Fotka
    {
        $this->rozcestnikUpdate = $rozcestnikUpdate;

        return $this;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function setOwner(string $owner): Fotka
    {
        $this->owner = $owner;

        return $this;
    }


}
