<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/** Catalog writes keep products, stock, and the stock audit trail in one transaction. */
final class AdminProduct
{
    public function __construct(private readonly Connection $database, private readonly string $projectDir)
    {
    }

    public function save(array $input, ?int $adminId): string
    {
        $id = trim((string) ($input['product_id'] ?? ''));
        $creating = ($input['mode'] ?? '') === 'create';
        $name = trim((string) ($input['name'] ?? ''));
        $type = trim((string) ($input['type'] ?? 'physical'));
        $priceInput = trim((string) ($input['base_price'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $image = trim((string) ($input['image'] ?? '')) ?: 'assets/images/logo-clean.png';
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,49}$/', $id)) {
            throw new \InvalidArgumentException('Product ID must be 2 to 50 lowercase letters, numbers, or hyphens.');
        }
        if ($name === '' || mb_strlen($name) > 255 || mb_strlen($description) > 5000) {
            throw new \InvalidArgumentException('Enter a name up to 255 characters and description up to 5000 characters.');
        }
        if (!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/', $priceInput) || (float) $priceInput > 99999999.99) {
            throw new \InvalidArgumentException('Enter a valid nonnegative price with up to two decimal places.');
        }
        if (!preg_match('~^assets/images/[a-zA-Z0-9 _.-]+\.(?:png|jpg|jpeg|webp|gif)$~i', $image)
            || !is_file($this->projectDir.'/public/'.$image)) {
            throw new \InvalidArgumentException('Choose an existing image under assets/images.');
        }
        $existing = $this->database->fetchAssociative('SELECT id, type FROM products WHERE id = :id', ['id' => $id]);
        if ($creating === (bool) $existing) {
            throw new \InvalidArgumentException($creating ? 'Product ID already exists.' : 'Product not found.');
        }
        if (!$creating) {
            $type = (string) $existing['type'];
        } elseif (!in_array($type, ['physical', 'service', 'custom'], true)) {
            throw new \InvalidArgumentException('Invalid product type.');
        }
        if ($type === 'custom' && (float) $priceInput !== 0.0) {
            throw new \InvalidArgumentException('Custom quote offerings must have a zero catalog price.');
        }
        $other = $this->database->fetchOne('SELECT id FROM products WHERE name = :name AND id <> :id LIMIT 1', ['name' => $name, 'id' => $id]);
        if ($other !== false) {
            throw new \InvalidArgumentException('Another product already uses this name.');
        }
        $stock = $minimum = $maximum = $reorder = 0;
        $expectedStock = 0;
        $sku = '';
        if ($type === 'physical') {
            $sku = trim((string) ($input['sku'] ?? ''));
            if (!preg_match('/^[a-zA-Z0-9-]{2,50}$/', $sku)) {
                throw new \InvalidArgumentException('SKU must be 2 to 50 letters, numbers, or hyphens.');
            }
            foreach (['current_stock', 'min_stock_level', 'max_stock_level', 'reorder_point'] as $field) {
                if (!preg_match('/^\d{1,7}$/', trim((string) ($input[$field] ?? '')))) {
                    throw new \InvalidArgumentException('Enter valid nonnegative stock and threshold values.');
                }
            }
            [$stock, $minimum, $maximum, $reorder] = array_map(
                static fn (string $field): int => (int) $input[$field],
                ['current_stock', 'min_stock_level', 'max_stock_level', 'reorder_point'],
            );
            if (!$creating) {
                $expectedInput = trim((string) ($input['expected_stock'] ?? ''));
                if (!preg_match('/^\d{1,7}$/', $expectedInput)) {
                    throw new \InvalidArgumentException('Refresh the product editor before saving stock.');
                }
                $expectedStock = (int) $expectedInput;
            }
            if ($maximum < 1 || $minimum > $maximum || $reorder > $maximum || $stock > $maximum) {
                throw new \InvalidArgumentException('Stock and thresholds must fit within the maximum stock level.');
            }
            $otherSku = $this->database->fetchOne('SELECT product_id FROM inventory WHERE sku = :sku AND product_id <> :id LIMIT 1', ['sku' => $sku, 'id' => $id]);
            if ($otherSku !== false) {
                throw new \InvalidArgumentException('Another product already uses this SKU.');
            }
        }
        $this->database->transactional(function () use ($id, $creating, $name, $type, $priceInput, $description, $image, $stock, $expectedStock, $sku, $minimum, $maximum, $reorder, $adminId): void {
            $product = ['name' => $name, 'type' => $type, 'base_price' => $priceInput, 'description' => $description, 'image' => $image];
            if ($creating) {
                $this->database->insert('products', ['id' => $id, 'is_active' => 1] + $product);
            } else {
                $this->database->update('products', $product, ['id' => $id]);
            }
            if ($type !== 'physical') {
                return;
            }
            $old = $this->database->fetchAssociative('SELECT current_stock FROM inventory WHERE product_id = :id FOR UPDATE', ['id' => $id]);
            if ($old === false) {
                $this->database->insert('inventory', ['product_id' => $id, 'sku' => $sku, 'current_stock' => $stock,
                    'min_stock_level' => $minimum, 'max_stock_level' => $maximum, 'reorder_point' => $reorder]);
                $delta = $stock;
            } else {
                if (!$creating && (int) $old['current_stock'] !== $expectedStock) {
                    throw new \RuntimeException('Stock changed while editing. Refresh inventory and try again.');
                }
                $delta = $stock - (int) $old['current_stock'];
                $this->database->update('inventory', ['sku' => $sku, 'current_stock' => $stock,
                    'min_stock_level' => $minimum, 'max_stock_level' => $maximum, 'reorder_point' => $reorder], ['product_id' => $id]);
            }
            if ($delta !== 0) {
                $this->database->insert('inventory_transactions', ['product_id' => $id, 'change_amount' => $delta,
                    'transaction_type' => 'manual_adjustment', 'reference_id' => 'CATALOG-EDIT',
                    'notes' => $creating ? 'Opening stock for new product' : 'Stock changed in catalog editor', 'created_by' => $adminId]);
            }
        });
        return $creating ? 'Product created.' : 'Product updated.';
    }

    public function setActive(string $id, bool $active): string
    {
        $changed = $this->database->executeStatement('UPDATE products SET is_active = :active WHERE id = :id', ['active' => (int) $active, 'id' => $id]);
        if ($changed === 0) {
            throw new \InvalidArgumentException('Product not found or already in that state.');
        }
        return $active ? 'Product restored to the catalog.' : 'Product archived from the storefront.';
    }
}
