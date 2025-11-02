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

    public function __construct(Mushroom $mushroom, string $tokenHash, ?\DateTimeImmutable $expiresAt = null, ?string $sentToEmail = null)
    {
        $this->mushroom = $mushroom;
        $this->tokenHash = $tokenHash;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $expiresAt;
        $this->sentToEmail = $sentToEmail;
    }

    public function isUsable(): bool
    {
        if ($this->usedAt !== null) return false;
        if ($this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable()) return false;
        return true;
    }

    // getters...
    public function getTokenHash(): string { return $this->tokenHash; }
    public function getMushroom(): Mushroom { return $this->mushroom; }
    public function markUsed(): void { $this->usedAt = new \DateTimeImmutable(); }
}
