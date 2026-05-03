<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_mushroom_comment_editlink_token', columns: ['token_hash'])]
class MushroomCommentEditLink
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\ManyToOne(targetEntity: MushroomComment::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MushroomComment $mushroomComment;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sentToEmail = null;

    public function __construct(?\DateTimeImmutable $expiresAt = null, ?string $sentToEmail = null)
    {
        $this->expiresAt = $expiresAt;
        $this->sentToEmail = $sentToEmail;
    }

    public function getId(): ?int { return $this->id; }
    public function getTokenHash(): string { return $this->tokenHash; }
    public function setTokenHash(string $hash): self { $this->tokenHash = $hash; return $this; }
    public function getMushroomComment(): MushroomComment { return $this->mushroomComment; }
    public function setMushroomComment(MushroomComment $c): self { $this->mushroomComment = $c; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $d): self { $this->createdAt = $d; return $this; }
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $d): self { $this->expiresAt = $d; return $this; }
    public function getUsedAt(): ?\DateTimeImmutable { return $this->usedAt; }
    public function getSentToEmail(): ?string { return $this->sentToEmail; }

    public function isUsable(): bool
    {
        if ($this->usedAt !== null) {
            return false;
        }
        if ($this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable()) {
            return false;
        }
        return true;
    }

    public function markUsed(): void
    {
        $this->usedAt = new \DateTimeImmutable();
    }
}