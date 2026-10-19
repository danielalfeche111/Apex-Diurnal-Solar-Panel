<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final class AdminInventory
{
    public function __construct(private readonly Connection $database)
    {
    }

    public function items(): array
    {
        return $this->database->fetchAllAssociative(<<<'SQL'
            SELECT p.id AS product_id, p.name AS product_name, p.type AS product_type,
                   p.base_price, p.description, p.image, p.is_active, COALESCE(i.id, 0) AS inventory_id,
                   COALESCE(i.sku, 'N/A') AS sku, COALESCE(i.current_stock, 0) AS current_stock,
                   COALESCE(i.min_stock_level, 5) AS min_stock_level,
                   COALESCE(i.max_stock_level, 100) AS max_stock_level,
                   COALESCE(i.reorder_point, 10) AS reorder_point, i.last_updated
            FROM products p LEFT JOIN inventory i ON p.id = i.product_id
            ORDER BY (p.type = 'physical') DESC, p.name ASC
            SQL);
    }

    public function availability(int $orderId): array
    {
        $items = $this->database->fetchAllAssociative(<<<'SQL'
            SELECT oi.product_id, oi.product_name, oi.quantity AS ordered_qty,
                   COALESCE(i.current_stock, 0) AS current_stock, p.type AS product_type
            FROM order_items oi JOIN products p ON oi.product_id = p.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE oi.order_id = :oid AND p.type = 'physical'
            SQL, ['oid' => $orderId]);
        $shortages = [];
        foreach ($items as $item) {
            if ((int) $item['current_stock'] < (int) $item['ordered_qty']) {
                $shortages[] = sprintf('%s (SKU required: %d, Available: %d)', $item['product_name'], $item['ordered_qty'], $item['current_stock']);
            }
        }
        return ['can_fulfill' => $shortages === [], 'shortages' => $shortages, 'items' => $items];
    }

    public function deduct(int $orderId, ?int $adminId): void
    {
        $order = $this->database->fetchAssociative('SELECT order_number FROM orders WHERE id = :id', ['id' => $orderId]);
        if (!$order) {
            throw new \RuntimeException("Order #$orderId not found");
        }
        $check = $this->availability($orderId);
        $balances = $this->allocationBalances($order['order_number']);
        $allocated = 0;
        foreach ($check['items'] as $item) {
            $balance = $balances[$item['product_id']] ?? 0;
            if ($balance === -(int) $item['ordered_qty']) {
                ++$allocated;
            } elseif ($balance !== 0) {
                throw new \RuntimeException('Inconsistent inventory allocation for '.$item['product_name']);
            }
        }
        if ($allocated === count($check['items'])) {
            return;
        }
        if ($allocated !== 0) {
            throw new \RuntimeException('Incomplete inventory allocation for order '.$order['order_number']);
        }
        if (!$check['can_fulfill']) {
            throw new \RuntimeException('Insufficient inventory: '.implode('; ', $check['shortages']));
        }
        foreach ($check['items'] as $item) {
            $qty = (int) $item['ordered_qty'];
            $changed = $this->database->executeStatement('UPDATE inventory SET current_stock = current_stock - :qty WHERE product_id = :pid AND current_stock >= :qty', ['qty' => $qty, 'pid' => $item['product_id']]);
            if ($changed !== 1) {
                throw new \RuntimeException('Insufficient inventory for '.$item['product_name']);
            }
            $this->database->insert('inventory_transactions', [
                'product_id' => $item['product_id'], 'change_amount' => -$qty,
                'transaction_type' => 'order_deduction', 'reference_id' => $order['order_number'],
                'notes' => 'Stock allocated for Order '.$order['order_number'], 'created_by' => $adminId,
            ]);
        }
    }

    public function restore(int $orderId, ?int $adminId): void
    {
        $order = $this->database->fetchAssociative('SELECT order_number FROM orders WHERE id = :id', ['id' => $orderId]);
        if (!$order) {
            throw new \RuntimeException("Order #$orderId not found");
        }
        foreach ($this->allocationBalances($order['order_number']) as $productId => $balance) {
            if ($balance === 0) {
                continue;
            }
            if ($balance > 0) {
                throw new \RuntimeException('Inconsistent inventory allocation for product '.$productId);
            }
            $qty = -$balance;
            $this->database->executeStatement('UPDATE inventory SET current_stock = current_stock + :qty WHERE product_id = :pid', ['qty' => $qty, 'pid' => $productId]);
            $this->database->insert('inventory_transactions', [
                'product_id' => $productId, 'change_amount' => $qty,
                'transaction_type' => 'cancellation_restock', 'reference_id' => $order['order_number'],
                'notes' => 'Reversed deduction following order cancellation/refund', 'created_by' => $adminId,
            ]);
        }
    }

    /** Net units still allocated to an order, including earlier cancellation cycles. */
    private function allocationBalances(string $orderNumber): array
    {
        $rows = $this->database->fetchAllAssociative(<<<'SQL'
            SELECT product_id, SUM(change_amount) AS balance
            FROM inventory_transactions
            WHERE reference_id = :ref
              AND transaction_type IN ('order_deduction', 'cancellation_restock')
            GROUP BY product_id
            SQL, ['ref' => $orderNumber]);
        $balances = [];
        foreach ($rows as $row) {
            $balances[$row['product_id']] = (int) $row['balance'];
        }
        return $balances;
    }

    public function adjust(string $productId, int $delta, string $reason, ?int $adminId): void
    {
        $this->database->transactional(function () use ($productId, $delta, $reason, $adminId): void {
            $current = $this->database->fetchOne('SELECT current_stock FROM inventory WHERE product_id = :pid FOR UPDATE', ['pid' => $productId]);
            if ($current === false) {
                throw new \RuntimeException("No inventory record found for product $productId");
            }
            $newStock = (int) $current + $delta;
            if ($newStock < 0) {
                throw new \RuntimeException("Cannot reduce stock below zero. Current stock is $current.");
            }
            $this->database->executeStatement('UPDATE inventory SET current_stock = :stock WHERE product_id = :pid', ['stock' => $newStock, 'pid' => $productId]);
            $this->database->insert('inventory_transactions', [
                'product_id' => $productId, 'change_amount' => $delta,
                'transaction_type' => 'manual_adjustment', 'reference_id' => 'MANUAL-ADJ',
                'notes' => $reason, 'created_by' => $adminId,
            ]);
        });
    }

    public function alerts(): array
    {
        return $this->database->fetchAllAssociative(<<<'SQL'
            SELECT p.name AS product_name, p.image, i.sku, i.current_stock,
                   i.min_stock_level, i.reorder_point, i.max_stock_level, i.last_updated
            FROM inventory i JOIN products p ON i.product_id = p.id
            WHERE i.current_stock <= i.reorder_point ORDER BY i.current_stock ASC
            SQL);
    }
}
