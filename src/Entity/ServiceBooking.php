<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ServiceBookingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceBookingRepository::class)]
#[ORM\Table(name: 'service_bookings')]
#[ORM\UniqueConstraint(name: 'booking_reference', columns: ['booking_reference'])]
#[ORM\Index(name: 'idx_service_bookings_date', columns: ['preferred_date'])]
#[ORM\Index(name: 'idx_service_bookings_ref', columns: ['booking_reference'])]
#[ORM\Index(name: 'idx_service_bookings_status', columns: ['status'])]
#[ORM\Index(name: 'idx_service_bookings_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'order_id', columns: ['order_id'])]
class ServiceBooking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'booking_reference', type: Types::STRING, length: 30)]
    private string $bookingReference;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Order $order = null;

    #[ORM\Column(name: 'customer_name', type: Types::STRING, length: 255)]
    private string $customerName;

    #[ORM\Column(name: 'customer_email', type: Types::STRING, length: 255)]
    private string $customerEmail;

    #[ORM\Column(name: 'customer_phone', type: Types::STRING, length: 50)]
    private string $customerPhone;

    #[ORM\Column(name: 'service_type', type: Types::ENUM, options: ['values' => ['consultation', 'installation', 'maintenance'], 'default' => 'consultation'])]
    private string $serviceType = 'consultation';

    #[ORM\Column(name: 'preferred_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $preferredDate;

    #[ORM\Column(name: 'preferred_time_slot', type: Types::STRING, length: 50)]
    private string $preferredTimeSlot;

    #[ORM\Column(name: 'status', type: Types::ENUM, nullable: true, options: ['values' => ['pending', 'confirmed', 'completed', 'cancelled'], 'default' => 'pending'])]
    private ?string $status = 'pending';

    #[ORM\Column(name: 'address', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(name: 'access_notes', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $accessNotes = null;

    #[ORM\Column(name: 'assigned_technician', type: Types::STRING, length: 255, nullable: true)]
    private ?string $assignedTechnician = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getBookingReference(): string
    {
        return $this->bookingReference;
    }

    public function setBookingReference(string $bookingReference): self
    {
        $this->bookingReference = $bookingReference;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): self
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(string $customerEmail): self
    {
        $this->customerEmail = $customerEmail;

        return $this;
    }

    public function getCustomerPhone(): string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(string $customerPhone): self
    {
        $this->customerPhone = $customerPhone;

        return $this;
    }

    public function getServiceType(): string
    {
        return $this->serviceType;
    }

    public function setServiceType(string $serviceType): self
    {
        $this->serviceType = $serviceType;

        return $this;
    }

    public function getPreferredDate(): \DateTimeImmutable
    {
        return $this->preferredDate;
    }

    public function setPreferredDate(\DateTimeImmutable $preferredDate): self
    {
        $this->preferredDate = $preferredDate;

        return $this;
    }

    public function getPreferredTimeSlot(): string
    {
        return $this->preferredTimeSlot;
    }

    public function setPreferredTimeSlot(string $preferredTimeSlot): self
    {
        $this->preferredTimeSlot = $preferredTimeSlot;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getAccessNotes(): ?string
    {
        return $this->accessNotes;
    }

    public function setAccessNotes(?string $accessNotes): self
    {
        $this->accessNotes = $accessNotes;

        return $this;
    }

    public function getAssignedTechnician(): ?string
    {
        return $this->assignedTechnician;
    }

    public function setAssignedTechnician(?string $assignedTechnician): self
    {
        $this->assignedTechnician = $assignedTechnician;

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
