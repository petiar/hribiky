<?php

// src/Entity/AccessLog.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AccessLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 45)]
    private string $ipAddress;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $accessedAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityClass = null;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $path = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    public function __construct(
        string $ipAddress,
        ?string $entityClass = null,
        ?int $entityId = null,
        ?string $path = null,
        ?string $userAgent = null
    ) {
        $this->ipAddress = $ipAddress;
        $this->entityClass = $entityClass;
        $this->entityId = $entityId;
        $this->path = $path;
        $this->userAgent = $userAgent;
        $this->accessedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): AccessLog
    {
        $this->id = $id;

        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): AccessLog
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getAccessedAt(): \DateTimeImmutable
    {
        return $this->accessedAt;
    }

    public function setAccessedAt(\DateTimeImmutable $accessedAt): AccessLog
    {
        $this->accessedAt = $accessedAt;

        return $this;
    }

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function setEntityClass(?string $entityClass): AccessLog
    {
        $this->entityClass = $entityClass;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): AccessLog
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): AccessLog
    {
        $this->path = $path;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): AccessLog
    {
        $this->userAgent = $userAgent;

        return $this;
    }
}
