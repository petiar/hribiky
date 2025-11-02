<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Mushroom
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "string", length: 16, nullable: true)]
    private ?string $country = null;

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

    #[ORM\OneToMany(mappedBy: "mushroom", targetEntity: MushroomComment::class, cascade: ["remove"])]
    private Collection $updates;

    #[ORM\OneToMany(mappedBy: "mushroom", targetEntity: Photo::class, cascade: [
        "persist",
        "remove",
    ])]
    private Collection $photos;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $source = null;

    #[ORM\OneToOne(mappedBy: 'mushroom', targetEntity: MushroomEditLink::class, cascade: ['remove'])]
    private ?MushroomEditLink $editLink = null;

    public function getEditLink(): ?MushroomEditLink
    {
        return $this->editLink;
    }

    public function setEditLink(?MushroomEditLink $l): self
    {
        $this->editLink = $l;

        return $this;
    }


    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->photos = new ArrayCollection();
        $this->updates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Mushroom
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Mushroom
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): Mushroom
    {
        $this->description = $description;

        return $this;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): Mushroom
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): Mushroom
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getAltitude(): ?float
    {
        return $this->altitude;
    }

    public function setAltitude(?float $altitude): Mushroom
    {
        $this->altitude = $altitude;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): Mushroom
    {
        $this->published = $published;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): Mushroom
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdates(): Collection
    {
        return $this->updates;
    }

    public function setUpdates(Collection $updates): Mushroom
    {
        $this->updates = $updates;

        return $this;
    }

    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addFotka(Photo $foto): self
    {
        if (!$this->photos->contains($foto)) {
            $this->photos->add($foto);
            $foto->setMushroom($this);
        }

        return $this;
    }

    public function removeFotka(Photo $foto): self
    {
        if ($this->photos->removeElement($foto)) {
            if ($foto->getMushroom() === $this) {
                $foto->setMushroom(null);
            }
        }

        return $this;
    }

    public function setPhotos(Collection $photos): Mushroom
    {
        $this->photos = $photos;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): Mushroom
    {
        $this->title = $title;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): Mushroom
    {
        $this->email = $email;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): Mushroom
    {
        $this->country = $country;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }


}
