<?php

namespace App\Entity;

use App\Enum\FeedbackStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'feedback')]
class Feedback
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private ?string $text = null;

    /** Nepovinný e-mail odosielateľa, aby sa mu dalo odpovedať. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: FeedbackStatus::class)]
    private FeedbackStatus $status;

    #[ORM\Column(type: "boolean")]
    private bool $published = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        // Stĺpec je typu „datetime“ (mutable) — Doctrine DBAL 4 DateTimeImmutable odmietne.
        $this->createdAt = new \DateTime();
        $this->status = FeedbackStatus::NotRead;
    }

    // --- getters a setters ---
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email !== null && trim($email) !== '' ? trim($email) : null;

        return $this;
    }

    public function getStatus(): FeedbackStatus
    {
        return $this->status;
    }

    public function setStatus(FeedbackStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): Feedback
    {
        $this->published = $published;

        return $this;
    }
}
