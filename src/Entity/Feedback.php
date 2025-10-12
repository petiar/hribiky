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

    #[ORM\Column(type: Types::STRING, length: 20, enumType: FeedbackStatus::class)]
    private FeedbackStatus $status;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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
}
