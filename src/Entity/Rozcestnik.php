<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Rozcestnik
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "float")]
    private float $latitude;

    #[ORM\Column(type: "float")]
    private float $longitude;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $altitude = null; // nadmorská výška

    #[ORM\Column(type: "boolean")]
    private bool $published = false;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToMany(mappedBy: "rozcestnik", targetEntity: RozcestnikUpdate::class, cascade: ["remove"])]
    private Collection $updates;

    #[ORM\OneToMany(mappedBy: "rozcestnik", targetEntity: Fotka::class, cascade: ["persist", "remove"])]
    private Collection $fotky;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->fotky = new ArrayCollection();
        $this->updates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Rozcestnik
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Rozcestnik
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): Rozcestnik
    {
        $this->description = $description;

        return $this;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): Rozcestnik
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): Rozcestnik
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getAltitude(): ?float
    {
        return $this->altitude;
    }

    public function setAltitude(?float $altitude): Rozcestnik
    {
        $this->altitude = $altitude;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): Rozcestnik
    {
        $this->published = $published;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): Rozcestnik
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdates(): Collection
    {
        return $this->updates;
    }

    public function setUpdates(Collection $updates): Rozcestnik
    {
        $this->updates = $updates;

        return $this;
    }

    public function getFotky(): Collection
    {
        return $this->fotky;
    }

    public function addFotka(Fotka $foto): self
    {
        if (!$this->fotky->contains($foto)) {
            $this->fotky->add($foto);
            $foto->setRozcestnik($this);
        }
        return $this;
    }

    public function removeFotka(Fotka $foto): self
    {
        if ($this->fotky->removeElement($foto)) {
            if ($foto->getRozcestnik() === $this) {
                $foto->setRozcestnik(null);
            }
        }
        return $this;
    }

    public function setFotky(Collection $fotky): Rozcestnik
    {
        $this->fotky = $fotky;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): Rozcestnik
    {
        $this->title = $title;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): Rozcestnik
    {
        $this->email = $email;

        return $this;
    }
}
