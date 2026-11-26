<?php

namespace App\Entity;

use App\Enum\BadgeType;
use App\Repository\UserBadgeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserBadgeRepository::class)]
#[ORM\Table(name: 'user_badge')]
#[ORM\UniqueConstraint(name: 'uniq_user_badge_user_type', columns: ['user_id', 'type'])]
class UserBadge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: BadgeType::class)]
    private BadgeType $type;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $value = null;

    #[ORM\ManyToOne(targetEntity: Mushroom::class)]
    #[ORM\JoinColumn(name: 'evidence_mushroom_id', nullable: true, onDelete: 'SET NULL')]
    private ?Mushroom $evidenceMushroom = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $awardedAt;

    public function __construct()
    {
        $this->awardedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): BadgeType
    {
        return $this->type;
    }

    public function setType(BadgeType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(?float $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getEvidenceMushroom(): ?Mushroom
    {
        return $this->evidenceMushroom;
    }

    public function setEvidenceMushroom(?Mushroom $evidenceMushroom): self
    {
        $this->evidenceMushroom = $evidenceMushroom;

        return $this;
    }

    public function getAwardedAt(): \DateTimeImmutable
    {
        return $this->awardedAt;
    }

    public function setAwardedAt(\DateTimeImmutable $awardedAt): self
    {
        $this->awardedAt = $awardedAt;

        return $this;
    }
}
