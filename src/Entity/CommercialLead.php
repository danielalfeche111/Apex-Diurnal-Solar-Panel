<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommercialLeadRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommercialLeadRepository::class)]
#[ORM\Table(name: 'commercial_leads')]
#[ORM\Index(name: 'idx_commercial_leads_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_commercial_leads_email', columns: ['corporate_email'])]
#[ORM\Index(name: 'idx_commercial_leads_type', columns: ['lead_type'])]
#[ORM\Index(name: 'idx_commercial_leads_user_id', columns: ['user_id'])]
class CommercialLead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'lead_type', type: Types::ENUM, options: ['values' => ['consultation', 'rfq'], 'default' => 'consultation'])]
    private string $leadType = 'consultation';

    #[ORM\Column(name: 'company_name', type: Types::STRING, length: 255)]
    private string $companyName;

    #[ORM\Column(name: 'business_registration_type', type: Types::STRING, length: 100, nullable: true)]
    private ?string $businessRegistrationType = null;

    #[ORM\Column(name: 'property_address', type: Types::TEXT, length: 65535)]
    private string $propertyAddress;

    #[ORM\Column(name: 'target_timeline', type: Types::ENUM, nullable: true, options: ['values' => ['Immediate', 'Within 3 Months', '6+ Months']])]
    private ?string $targetTimeline = null;

    #[ORM\Column(name: 'facility_type', type: Types::ENUM, nullable: true, options: ['values' => ['Manufacturing Plant', 'Commercial Building', 'Warehouse', 'Agricultural', 'School', 'House', 'House / Residential', 'Commercial']])]
    private ?string $facilityType = null;

    #[ORM\Column(name: 'power_supply', type: Types::STRING, length: 50, nullable: true, options: ['default' => 'Three-Phase Supply'])]
    private ?string $powerSupply = 'Three-Phase Supply';

    #[ORM\Column(name: 'facility_size', type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00', 'comment' => 'Facility size in sqm'])]
    private string $facilitySize = '0.00';

    #[ORM\Column(name: 'current_monthly_bill', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00', 'comment' => 'Monthly electricity bill PHP'])]
    private string $currentMonthlyBill = '0.00';

    #[ORM\Column(name: 'estimated_system_size', type: Types::DECIMAL, precision: 6, scale: 2, nullable: true, options: ['comment' => 'Recommended system kW'])]
    private ?string $estimatedSystemSize = null;

    #[ORM\Column(name: 'estimated_installation_cost', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true, options: ['comment' => 'PHP'])]
    private ?string $estimatedInstallationCost = null;

    #[ORM\Column(name: 'estimated_annual_savings', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true, options: ['comment' => 'PHP per year'])]
    private ?string $estimatedAnnualSavings = null;

    #[ORM\Column(name: 'estimated_payback_period', type: Types::DECIMAL, precision: 4, scale: 1, nullable: true, options: ['comment' => 'years'])]
    private ?string $estimatedPaybackPeriod = null;

    #[ORM\Column(name: 'applicable_discounts', type: Types::TEXT, nullable: true)]
    private ?string $applicableDiscounts = null;

    #[ORM\Column(name: 'estimated_installation_timeline', type: Types::STRING, length: 100, nullable: true)]
    private ?string $estimatedInstallationTimeline = null;

    #[ORM\Column(name: 'contact_person', type: Types::STRING, length: 255)]
    private string $contactPerson;

    #[ORM\Column(name: 'contact_title', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactTitle = null;

    #[ORM\Column(name: 'corporate_email', type: Types::STRING, length: 255)]
    private string $corporateEmail;

    #[ORM\Column(name: 'phone_number', type: Types::STRING, length: 20)]
    private string $phoneNumber;

    #[ORM\Column(name: 'best_call_time', type: Types::STRING, length: 50, nullable: true)]
    private ?string $bestCallTime = null;

    #[ORM\Column(name: 'preferred_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $preferredDate = null;

    #[ORM\Column(name: 'preferred_time_slot', type: Types::STRING, length: 50, nullable: true)]
    private ?string $preferredTimeSlot = null;

    #[ORM\Column(name: 'access_notes', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $accessNotes = null;

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

    public function getLeadType(): string
    {
        return $this->leadType;
    }

    public function setLeadType(string $leadType): self
    {
        $this->leadType = $leadType;

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

    public function getBusinessRegistrationType(): ?string
    {
        return $this->businessRegistrationType;
    }

    public function setBusinessRegistrationType(?string $businessRegistrationType): self
    {
        $this->businessRegistrationType = $businessRegistrationType;

        return $this;
    }

    public function getPropertyAddress(): string
    {
        return $this->propertyAddress;
    }

    public function setPropertyAddress(string $propertyAddress): self
    {
        $this->propertyAddress = $propertyAddress;

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

    public function getFacilityType(): ?string
    {
        return $this->facilityType;
    }

    public function setFacilityType(?string $facilityType): self
    {
        $this->facilityType = $facilityType;

        return $this;
    }

    public function getPowerSupply(): ?string
    {
        return $this->powerSupply;
    }

    public function setPowerSupply(?string $powerSupply): self
    {
        $this->powerSupply = $powerSupply;

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

    public function getEstimatedInstallationTimeline(): ?string
    {
        return $this->estimatedInstallationTimeline;
    }

    public function setEstimatedInstallationTimeline(?string $estimatedInstallationTimeline): self
    {
        $this->estimatedInstallationTimeline = $estimatedInstallationTimeline;

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

    public function getContactTitle(): ?string
    {
        return $this->contactTitle;
    }

    public function setContactTitle(?string $contactTitle): self
    {
        $this->contactTitle = $contactTitle;

        return $this;
    }

    public function getCorporateEmail(): string
    {
        return $this->corporateEmail;
    }

    public function setCorporateEmail(string $corporateEmail): self
    {
        $this->corporateEmail = $corporateEmail;

        return $this;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getBestCallTime(): ?string
    {
        return $this->bestCallTime;
    }

    public function setBestCallTime(?string $bestCallTime): self
    {
        $this->bestCallTime = $bestCallTime;

        return $this;
    }

    public function getPreferredDate(): ?\DateTimeImmutable
    {
        return $this->preferredDate;
    }

    public function setPreferredDate(?\DateTimeImmutable $preferredDate): self
    {
        $this->preferredDate = $preferredDate;

        return $this;
    }

    public function getPreferredTimeSlot(): ?string
    {
        return $this->preferredTimeSlot;
    }

    public function setPreferredTimeSlot(?string $preferredTimeSlot): self
    {
        $this->preferredTimeSlot = $preferredTimeSlot;

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
