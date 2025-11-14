<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_mushroom_editlink_token', columns: ['token_hash'])]
class MushroomEditLink
{

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\OneToOne(inversedBy: 'editLink', targetEntity: Mushroom::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Mushroom $mushroom;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sentToEmail = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): MushroomEditLink
    {
        $this->id = $id;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt
    ): MushroomEditLink {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt
    ): MushroomEditLink {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function setUsedAt(?\DateTimeImmutable $usedAt): MushroomEditLink
    {
        $this->usedAt = $usedAt;

        return $this;
    }

    public function getSentToEmail(): ?string
    {
        return $this->sentToEmail;
    }

    public function setSentToEmail(?string $sentToEmail): MushroomEditLink
    {
        $this->sentToEmail = $sentToEmail;

        return $this;
    }

    public function __construct(
        ?\DateTimeImmutable $expiresAt = null,
        ?string $sentToEmail = null
    ) {
        $this->expiresAt = $expiresAt;
        $this->sentToEmail = $sentToEmail;
    }

    public function isUsable(): bool
    {
        if ($this->usedAt !== null) {
            return false;
        }
        if ($this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable(
            )) {
            return false;
        }

        return true;
    }

    // getters...
    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getMushroom(): Mushroom
    {
        return $this->mushroom;
    }

    public function markUsed(): void
    {
        $this->usedAt = new \DateTimeImmutable();
    }

    public function setMushroom(Mushroom $mushroom): MushroomEditLink
    {
        $this->mushroom = $mushroom;
        return $this;
    }

    public function setTokenHash(string $hash): MushroomEditLink
    {
        $this->tokenHash = $hash;
        return $this;
    }
}
