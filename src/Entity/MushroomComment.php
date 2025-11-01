<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class MushroomComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "boolean")]
    private bool $published = false;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: Mushroom::class, inversedBy: "updates")]
    #[ORM\JoinColumn(nullable: false)]
    private Mushroom $mushroom;

    #[ORM\OneToMany(mappedBy: "mushroomComment", targetEntity: Photo::class, cascade: ["persist", "remove"])]
    private Collection $photos;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $source = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->photos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): MushroomComment
    {
        $this->id = $id;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): MushroomComment
    {
        $this->description = $description;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): MushroomComment
    {
        $this->published = $published;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt
    ): MushroomComment {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getMushroom(): Mushroom
    {
        return $this->mushroom;
    }

    public function setMushroom(Mushroom $mushroom): MushroomComment
    {
        $this->mushroom = $mushroom;

        return $this;
    }

    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function setPhotos(Collection $photos): MushroomComment
    {
        $this->photos = $photos;

        return $this;
    }

    public function addFotka(Photo $foto): self
    {
        if (!$this->photos->contains($foto)) {
            $this->photos->add($foto);
            $foto->setMushroomComment($this);
        }

        return $this;
    }

    public function removeFotka(Photo $foto): self
    {
        if ($this->photos->removeElement($foto)) {
            if ($foto->getMushroomComment() === $this) {
                $foto->setMushroomComment(null);
            }
        }

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): MushroomComment
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): MushroomComment
    {
        $this->email = $email;

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
