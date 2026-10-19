<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\QuoteRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuoteRequestRepository::class)]
#[ORM\Table(name: 'quote_requests')]
#[ORM\Index(name: 'idx_quote_requests_email', columns: ['email'])]
#[ORM\Index(name: 'idx_quote_requests_lead_id', columns: ['lead_id'])]
#[ORM\Index(name: 'idx_quote_requests_num', columns: ['quote_number'])]
#[ORM\Index(name: 'idx_quote_requests_status', columns: ['status'])]
#[ORM\Index(name: 'idx_quote_requests_user_id', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'quote_number', columns: ['quote_number'])]
#[ORM\Index(name: 'quoted_by', columns: ['quoted_by'])]
class QuoteRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: CommercialLead::class)]
    #[ORM\JoinColumn(name: 'lead_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?CommercialLead $lead = null;

    #[ORM\Column(name: 'quote_number', type: Types::STRING, length: 30)]
    private string $quoteNumber;

    #[ORM\Column(name: 'company_name', type: Types::STRING, length: 255)]
    private string $companyName;

    #[ORM\Column(name: 'contact_person', type: Types::STRING, length: 255)]
    private string $contactPerson;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\Column(name: 'phone', type: Types::STRING, length: 50)]
    private string $phone;

    #[ORM\Column(name: 'facility_type', type: Types::STRING, length: 100, nullable: true)]
    private ?string $facilityType = null;

    #[ORM\Column(name: 'facility_size', type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $facilitySize = '0.00';

    #[ORM\Column(name: 'current_monthly_bill', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $currentMonthlyBill = '0.00';

    #[ORM\Column(name: 'target_timeline', type: Types::ENUM, nullable: true, options: ['values' => ['Immediate', 'Within 3 Months', '6+ Months']])]
    private ?string $targetTimeline = null;

    #[ORM\Column(name: 'estimated_system_size', type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $estimatedSystemSize = null;

    #[ORM\Column(name: 'estimated_installation_cost', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $estimatedInstallationCost = null;

    #[ORM\Column(name: 'estimated_annual_savings', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $estimatedAnnualSavings = null;

    #[ORM\Column(name: 'estimated_payback_period', type: Types::DECIMAL, precision: 4, scale: 1, nullable: true)]
    private ?string $estimatedPaybackPeriod = null;

    #[ORM\Column(name: 'applicable_discounts', type: Types::TEXT, nullable: true, options: ['comment' => 'JSON array'])]
    private ?string $applicableDiscounts = null;

    #[ORM\Column(name: 'installation_address', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $installationAddress = null;

    #[ORM\Column(name: 'access_notes', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $accessNotes = null;

    #[ORM\Column(name: 'installation_head', type: Types::STRING, length: 255, nullable: true)]
    private ?string $installationHead = null;

    #[ORM\Column(name: 'installation_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $installationDate = null;

    #[ORM\Column(name: 'status', type: Types::ENUM, nullable: true, options: ['values' => ['new', 'pending', 'client_confirmed', 'confirmed', 'in_progress', 'completed', 'cancelled', 'reviewed', 'quoted', 'accepted', 'rejected', 'expired'], 'default' => 'new'])]
    private ?string $status = 'new';

    #[ORM\Column(name: 'admin_notes', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $adminNotes = null;

    #[ORM\Column(name: 'quoted_amount', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $quotedAmount = null;

    #[ORM\ManyToOne(targetEntity: AdminUser::class)]
    #[ORM\JoinColumn(name: 'quoted_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AdminUser $quoted = null;

    #[ORM\Column(name: 'quoted_at', type: Types::DATETIME_IMMUTABLE, nullable: true, columnDefinition: 'TIMESTAMP NULL')]
    private ?\DateTimeImmutable $quotedAt = null;

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

    public function getLead(): ?CommercialLead
    {
        return $this->lead;
    }

    public function setLead(?CommercialLead $lead): self
    {
        $this->lead = $lead;

        return $this;
    }

    public function getQuoteNumber(): string
    {
        return $this->quoteNumber;
    }

    public function setQuoteNumber(string $quoteNumber): self
    {
        $this->quoteNumber = $quoteNumber;

        return $this;
    }

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): self
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getContactPerson(): string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(string $contactPerson): self
    {
        $this->contactPerson = $contactPerson;

        return $this;
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

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getFacilityType(): ?string
    {
        return $this->facilityType;
    }

    public function setFacilityType(?string $facilityType): self
    {
        $this->facilityType = $facilityType;

        return $this;
    }

    public function getFacilitySize(): string
    {
        return $this->facilitySize;
    }

    public function setFacilitySize(string $facilitySize): self
    {
        $this->facilitySize = $facilitySize;

        return $this;
    }

    public function getCurrentMonthlyBill(): string
    {
        return $this->currentMonthlyBill;
    }

    public function setCurrentMonthlyBill(string $currentMonthlyBill): self
    {
        $this->currentMonthlyBill = $currentMonthlyBill;

        return $this;
    }

    public function getTargetTimeline(): ?string
    {
        return $this->targetTimeline;
    }

    public function setTargetTimeline(?string $targetTimeline): self
    {
        $this->targetTimeline = $targetTimeline;

        return $this;
    }

    public function getEstimatedSystemSize(): ?string
    {
        return $this->estimatedSystemSize;
    }

    public function setEstimatedSystemSize(?string $estimatedSystemSize): self
    {
        $this->estimatedSystemSize = $estimatedSystemSize;

        return $this;
    }

    public function getEstimatedInstallationCost(): ?string
    {
        return $this->estimatedInstallationCost;
    }

    public function setEstimatedInstallationCost(?string $estimatedInstallationCost): self
    {
        $this->estimatedInstallationCost = $estimatedInstallationCost;

        return $this;
    }

    public function getEstimatedAnnualSavings(): ?string
    {
        return $this->estimatedAnnualSavings;
    }

    public function setEstimatedAnnualSavings(?string $estimatedAnnualSavings): self
    {
        $this->estimatedAnnualSavings = $estimatedAnnualSavings;

        return $this;
    }

    public function getEstimatedPaybackPeriod(): ?string
    {
        return $this->estimatedPaybackPeriod;
    }

    public function setEstimatedPaybackPeriod(?string $estimatedPaybackPeriod): self
    {
        $this->estimatedPaybackPeriod = $estimatedPaybackPeriod;

        return $this;
    }

    public function getApplicableDiscounts(): ?string
    {
        return $this->applicableDiscounts;
    }

    public function setApplicableDiscounts(?string $applicableDiscounts): self
    {
        $this->applicableDiscounts = $applicableDiscounts;

        return $this;
    }

    public function getInstallationAddress(): ?string
    {
        return $this->installationAddress;
    }

    public function setInstallationAddress(?string $installationAddress): self
    {
        $this->installationAddress = $installationAddress;

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

    public function getInstallationHead(): ?string
    {
        return $this->installationHead;
    }

    public function setInstallationHead(?string $installationHead): self
    {
        $this->installationHead = $installationHead;

        return $this;
    }

    public function getInstallationDate(): ?\DateTimeImmutable
    {
        return $this->installationDate;
    }

    public function setInstallationDate(?\DateTimeImmutable $installationDate): self
    {
        $this->installationDate = $installationDate;

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

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): self
    {
        $this->adminNotes = $adminNotes;

        return $this;
    }

    public function getQuotedAmount(): ?string
    {
        return $this->quotedAmount;
    }

    public function setQuotedAmount(?string $quotedAmount): self
    {
        $this->quotedAmount = $quotedAmount;

        return $this;
    }

    public function getQuoted(): ?AdminUser
    {
        return $this->quoted;
    }

    public function setQuoted(?AdminUser $quoted): self
    {
        $this->quoted = $quoted;

        return $this;
    }

    public function getQuotedAt(): ?\DateTimeImmutable
    {
        return $this->quotedAt;
    }

    public function setQuotedAt(?\DateTimeImmutable $quotedAt): self
    {
        $this->quotedAt = $quotedAt;

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
