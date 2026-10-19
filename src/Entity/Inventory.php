<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InventoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryRepository::class)]
#[ORM\Table(name: 'inventory')]
#[ORM\UniqueConstraint(name: 'product_id', columns: ['product_id'])]
#[ORM\UniqueConstraint(name: 'sku', columns: ['sku'])]
class Inventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(name: 'sku', type: Types::STRING, length: 50)]
    private string $sku;

    #[ORM\Column(name: 'current_stock', type: Types::INTEGER, options: ['default' => 0])]
    private int $currentStock = 0;

    #[ORM\Column(name: 'min_stock_level', type: Types::INTEGER, options: ['default' => 5])]
    private int $minStockLevel = 5;

    #[ORM\Column(name: 'max_stock_level', type: Types::INTEGER, options: ['default' => 100])]
    private int $maxStockLevel = 100;

    #[ORM\Column(name: 'reorder_point', type: Types::INTEGER, options: ['default' => 10])]
    private int $reorderPoint = 10;

    #[ORM\Column(name: 'last_updated', type: Types::DATETIME_IMMUTABLE, columnDefinition: 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')]
    private \DateTimeImmutable $lastUpdated;

    public function __construct()
    {
        $this->lastUpdated = new \DateTimeImmutable();
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

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): self
    {
        $this->sku = $sku;

        return $this;
    }

    public function getCurrentStock(): int
    {
        return $this->currentStock;
    }

    public function setCurrentStock(int $currentStock): self
    {
        $this->currentStock = $currentStock;

        return $this;
    }

    public function getMinStockLevel(): int
    {
        return $this->minStockLevel;
    }

    public function setMinStockLevel(int $minStockLevel): self
    {
        $this->minStockLevel = $minStockLevel;

        return $this;
    }

    public function getMaxStockLevel(): int
    {
        return $this->maxStockLevel;
    }

    public function setMaxStockLevel(int $maxStockLevel): self
    {
        $this->maxStockLevel = $maxStockLevel;

        return $this;
    }

    public function getReorderPoint(): int
    {
        return $this->reorderPoint;
    }

    public function setReorderPoint(int $reorderPoint): self
    {
        $this->reorderPoint = $reorderPoint;

        return $this;
    }

    public function getLastUpdated(): \DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeImmutable $lastUpdated): self
    {
        $this->lastUpdated = $lastUpdated;

        return $this;
    }
}
