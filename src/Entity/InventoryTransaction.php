<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InventoryTransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryTransactionRepository::class)]
#[ORM\Table(name: 'inventory_transactions')]
#[ORM\Index(name: 'idx_inv_tx_created_by', columns: ['created_by'])]
#[ORM\Index(name: 'idx_inv_tx_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_inv_tx_type', columns: ['transaction_type'])]
class InventoryTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(name: 'change_amount', type: Types::INTEGER)]
    private int $changeAmount;

    #[ORM\Column(name: 'transaction_type', type: Types::ENUM, options: ['values' => ['order_deduction', 'cancellation_restock', 'manual_adjustment', 'restock']])]
    private string $transactionType;

    #[ORM\Column(name: 'reference_id', type: Types::STRING, length: 50, nullable: true, options: ['comment' => 'Order number or adjustment reason'])]
    private ?string $referenceId = null;

    #[ORM\Column(name: 'notes', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: AdminUser::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AdminUser $created = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, columnDefinition: 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getChangeAmount(): int
    {
        return $this->changeAmount;
    }

    public function setChangeAmount(int $changeAmount): self
    {
        $this->changeAmount = $changeAmount;

        return $this;
    }

    public function getTransactionType(): string
    {
        return $this->transactionType;
    }

    public function setTransactionType(string $transactionType): self
    {
        $this->transactionType = $transactionType;

        return $this;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function setReferenceId(?string $referenceId): self
    {
        $this->referenceId = $referenceId;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreated(): ?AdminUser
    {
        return $this->created;
    }

    public function setCreated(?AdminUser $created): self
    {
        $this->created = $created;

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
}
