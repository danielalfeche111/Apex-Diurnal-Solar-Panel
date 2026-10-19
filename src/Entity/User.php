<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'email', columns: ['email'])]
#[ORM\Index(name: 'idx_users_email', columns: ['email'])]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\Column(name: 'full_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $fullName = null;

    #[ORM\Column(name: 'phone', type: Types::STRING, length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'street_address', type: Types::STRING, length: 255, nullable: true)]
    private ?string $streetAddress = null;

    #[ORM\Column(name: 'city', type: Types::STRING, length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(name: 'province', type: Types::STRING, length: 100, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(name: 'postal_code', type: Types::STRING, length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(name: 'is_default_shipping', type: Types::BOOLEAN, nullable: true, options: ['default' => true])]
    private ?bool $isDefaultShipping = true;

    #[ORM\Column(name: 'is_default_billing', type: Types::BOOLEAN, nullable: true, options: ['default' => true])]
    private ?bool $isDefaultBilling = true;

    #[ORM\Column(name: 'password_hash', type: Types::STRING, length: 255)]
    private string $passwordHash;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, columnDefinition: 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, columnDefinition: 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): self
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getStreetAddress(): ?string
    {
        return $this->streetAddress;
    }

    public function setStreetAddress(?string $streetAddress): self
    {
        $this->streetAddress = $streetAddress;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): self
    {
        $this->province = $province;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getIsDefaultShipping(): ?bool
    {
        return $this->isDefaultShipping;
    }

    public function setIsDefaultShipping(?bool $isDefaultShipping): self
    {
        $this->isDefaultShipping = $isDefaultShipping;

        return $this;
    }

    public function getIsDefaultBilling(): ?bool
    {
        return $this->isDefaultBilling;
    }

    public function setIsDefaultBilling(?bool $isDefaultBilling): self
    {
        $this->isDefaultBilling = $isDefaultBilling;

        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
