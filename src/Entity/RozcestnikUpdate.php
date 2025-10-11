<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class RozcestnikUpdate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "boolean")]
    private bool $published = false;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: Rozcestnik::class, inversedBy: "updates")]
    #[ORM\JoinColumn(nullable: false)]
    private Rozcestnik $rozcestnik;

    #[ORM\OneToMany(mappedBy: "rozcestnikUpdate", targetEntity: Fotka::class, cascade: ["persist", "remove"])]
    private Collection $fotky;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->fotky = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): RozcestnikUpdate
    {
        $this->id = $id;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): RozcestnikUpdate
    {
        $this->description = $description;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): RozcestnikUpdate
    {
        $this->published = $published;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt
    ): RozcestnikUpdate {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRozcestnik(): Rozcestnik
    {
        return $this->rozcestnik;
    }

    public function setRozcestnik(Rozcestnik $rozcestnik): RozcestnikUpdate
    {
        $this->rozcestnik = $rozcestnik;

        return $this;
    }

    public function getFotky(): Collection
    {
        return $this->fotky;
    }

    public function setFotky(Collection $fotky): RozcestnikUpdate
    {
        $this->fotky = $fotky;

        return $this;
    }

    public function addFotka(Fotka $foto): self
    {
        if (!$this->fotky->contains($foto)) {
            $this->fotky->add($foto);
            $foto->setRozcestnikUpdate($this);
        }

        return $this;
    }

    public function removeFotka(Fotka $foto): self
    {
        if ($this->fotky->removeElement($foto)) {
            if ($foto->getRozcestnikUpdate() === $this) {
                $foto->setRozcestnikUpdate(null);
            }
        }

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): RozcestnikUpdate
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): RozcestnikUpdate
    {
        $this->email = $email;

        return $this;
    }
}
