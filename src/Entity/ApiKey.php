<?php
// src/Entity/ApiKey.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ApiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 64, unique: true)]
    private string $keyValue;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $validUntil;

    public function __construct(string $email, string $keyValue)
    {
        $this->email = $email;
        $this->keyValue = $keyValue;
        $this->createdAt = new \DateTimeImmutable();
        $this->validUntil = (new \DateTimeImmutable())->modify('+1 year');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): ApiKey
    {
        $this->id = $id;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): ApiKey
    {
        $this->email = $email;

        return $this;
    }

    public function getKeyValue(): string
    {
        return $this->keyValue;
    }

    public function setKeyValue(string $keyValue): ApiKey
    {
        $this->keyValue = $keyValue;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): ApiKey
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getValidUntil(): \DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(\DateTimeInterface $validUntil): ApiKey
    {
        $this->validUntil = $validUntil;

        return $this;
    }
}

