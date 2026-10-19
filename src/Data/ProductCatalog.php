<?php

declare(strict_types=1);

namespace App\Data;

use Doctrine\DBAL\Connection;

final class ProductCatalog
{
    private ?array $products = null;

    public function __construct(private readonly string $projectDir, private readonly Connection $database)
    {
    }

    public function all(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }
        $original = json_decode(
            (string) file_get_contents($this->projectDir.'/config/data/products.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $visuals = array_column($original, null, 'id');
        $baseline = json_decode((string) file_get_contents($this->projectDir.'/config/data/product_db_baseline.json'), true, 512, JSON_THROW_ON_ERROR);
        $rows = $this->database->fetchAllAssociative('SELECT id, name, type, base_price, description, image FROM products WHERE is_active = 1 ORDER BY created_at, id');
        $originalOrder = array_flip(array_keys($visuals));
        usort($rows, static fn (array $a, array $b): int => ($originalOrder[$a['id']] ?? PHP_INT_MAX) <=> ($originalOrder[$b['id']] ?? PHP_INT_MAX));
        $products = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $visual = $visuals[$id] ?? [];
            $type = (string) $row['type'];
            $price = (float) $row['base_price'];
            $originalPrice = isset($visual['price']) ? (float) preg_replace('/[^0-9.]/', '', $visual['price']) : null;
            $products[] = [
                'id' => $id,
                'title' => (string) $row['name'],
                'image' => (string) ($row['image'] ?: ($visual['image'] ?? '')),
                'alt' => $visual['alt'] ?? (string) $row['name'],
                'price' => $type === 'custom' ? 'Call for Quote' : ($originalPrice === $price ? $visual['price'] : '₱'.number_format($price, 2)),
                'badge' => $visual['badge'] ?? ($type === 'service' ? 'Professional Service' : 'Solar Product'),
                'description' => isset($visual['description']) && ($baseline[$id] ?? null) === $row['description']
                    ? $visual['description'] : (string) ($row['description'] ?: ($visual['description'] ?? '')),
                'specs' => $visual['specs'] ?? [],
                'actions' => $visual['actions'] ?? ($type === 'custom'
                    ? [['label' => 'CONTACT SALES', 'class' => 'btn btn-yellow btn-block']]
                    : ($type === 'service'
                        ? [['label' => 'BOOK NOW', 'class' => 'btn btn-yellow btn-block']]
                        : [['label' => 'BUY NOW', 'class' => 'btn btn-yellow'], ['label' => 'LEARN MORE', 'class' => 'btn btn-outline']])),
            ];
        }
        return $this->products = $products;
    }

    public function byId(): array
    {
        $map = [];
        foreach ($this->all() as $product) {
            $map[$product['id']] = $product;
        }

        return $map;
    }

    public function search(string $term): array
    {
        if ($term === '') {
            return [];
        }

        return array_values(array_filter($this->all(), static fn (array $product): bool =>
            stripos($product['title'], $term) !== false || stripos($product['alt'], $term) !== false
        ));
    }
}
