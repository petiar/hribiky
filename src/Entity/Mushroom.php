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

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $altitude = null; // nadmorská výška

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

    #[ORM\Column(type: 'boolean')]
    private bool $blogPostGenerated = false;

    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $approvalToken = null;

    #[ORM\OneToMany(mappedBy: 'mushroom', targetEntity: MushroomArticleLink::class, cascade: ['persist', 'remove'])]
    private Collection $articleLinks;

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
        $this->articleLinks = new ArrayCollection();
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

    public function getAltitude(): ?int
    {
        return $this->altitude;
    }

    public function setAltitude(?float $altitude): Mushroom
    {
        $this->altitude = $altitude !== null ? (int) round($altitude) : null;

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

    public function isBlogPostGenerated(): bool
    {
        return $this->blogPostGenerated;
    }

    public function setBlogPostGenerated(bool $blogPostGenerated): self
    {
        $this->blogPostGenerated = $blogPostGenerated;

        return $this;
    }

    public function getArticleLinks(): Collection
    {
        return $this->articleLinks;
    }

    public function addArticleLink(MushroomArticleLink $link): self
    {
        if (!$this->articleLinks->contains($link)) {
            $this->articleLinks->add($link);
            $link->setMushroom($this);
        }

        return $this;
    }

    public function removeArticleLink(MushroomArticleLink $link): self
    {
        $this->articleLinks->removeElement($link);

        return $this;
    }

    public function getApprovalToken(): ?string
    {
        return $this->approvalToken;
    }

    public function setApprovalToken(?string $approvalToken): self
    {
        $this->approvalToken = $approvalToken;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getLastModified(): \DateTimeInterface
    {
        $latest = $this->createdAt;

        /**
         * @var $comment \App\Entity\MushroomComment
         */
        foreach ($this->updates as $comment) {
            if (!$comment->isPublished()) {
                continue;
            }
            if ($comment->getCreatedAt() > $latest) {
                $latest = $comment->getCreatedAt();
            }
        }

        return $latest;
    }

}
