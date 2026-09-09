<?php
/**
 * admin/inventory/sync.php - Real-Time Inventory Tracking & Stock Synchronization Logic
 */

require_once __DIR__ . '/../auth.php';

/**
 * Fetch all inventory items joined with product metadata
 * @return array
 */
function getInventoryItems(): array {
    $db = getConnection();
    if (!$db) return [];

    $sql = "
        SELECT 
            p.id AS product_id,
            p.name AS product_name,
            p.type AS product_type,
            p.base_price,
            p.image,
            COALESCE(i.id, 0) AS inventory_id,
            COALESCE(i.sku, 'N/A') AS sku,
            COALESCE(i.current_stock, 0) AS current_stock,
            COALESCE(i.min_stock_level, 5) AS min_stock_level,
            COALESCE(i.max_stock_level, 100) AS max_stock_level,
            COALESCE(i.reorder_point, 10) AS reorder_point,
            i.last_updated
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        ORDER BY (p.type = 'physical') DESC, p.name ASC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if all physical products in an order have sufficient stock
 * @param int $orderId
 * @return array ['can_fulfill' => bool, 'shortages' => array]
 */
function checkOrderStockAvailability(int $orderId): array {
    $db = getConnection();
    if (!$db) return ['can_fulfill' => false, 'shortages' => ['Database connection unavailable']];

    $sql = "
        SELECT 
            oi.product_id,
            oi.product_name,
            oi.quantity AS ordered_qty,
            COALESCE(i.current_stock, 0) AS current_stock,
            p.type AS product_type
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE oi.order_id = :oid AND p.type = 'physical'
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':oid' => $orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $shortages = [];
    foreach ($items as $item) {
        if ($item['current_stock'] < $item['ordered_qty']) {
            $shortages[] = sprintf(
                "%s (SKU required: %d, Available: %d)",
                $item['product_name'],
                $item['ordered_qty'],
                $item['current_stock']
            );
        }
    }

    return [
        'can_fulfill' => empty($shortages),
        'shortages' => $shortages,
        'items' => $items
    ];
}

/**
 * Deduct inventory stock for physical items in an order and record transactions
 * @param int $orderId
 * @param int|null $adminId
 * @return bool
 * @throws Exception
 */
function deductStockForOrder(int $orderId, ?int $adminId = null): bool {
    $db = getConnection();
    if (!$db) throw new Exception("Database unavailable");

    // Fetch order number
    $stmtOrd = $db->prepare("SELECT order_number, status FROM orders WHERE id = :oid");
    $stmtOrd->execute([':oid' => $orderId]);
    $order = $stmtOrd->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception("Order #$orderId not found");

    // Check availability first
    $check = checkOrderStockAvailability($orderId);
    if (!$check['can_fulfill']) {
        throw new Exception("Insufficient inventory: " . implode("; ", $check['shortages']));
    }

    $db->beginTransaction();
    try {
        $updateInv = $db->prepare("UPDATE inventory SET current_stock = current_stock - :qty WHERE product_id = :pid");
        $logTx = $db->prepare("
            INSERT INTO inventory_transactions (product_id, change_amount, transaction_type, reference_id, notes, created_by)
            VALUES (:pid, :delta, 'order_deduction', :ref, :notes, :admin_id)
        ");

        foreach ($check['items'] as $it) {
            $qty = (int)$it['ordered_qty'];
            $pid = $it['product_id'];

            // Deduct stock
            $updateInv->execute([':qty' => $qty, ':pid' => $pid]);

            // Log transaction
            $logTx->execute([
                ':pid' => $pid,
                ':delta' => -$qty,
                ':ref' => $order['order_number'],
                ':notes' => "Stock allocated for Order {$order['order_number']}",
                ':admin_id' => $adminId
            ]);
        }

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Restore inventory stock for physical items in a cancelled or refunded order
 * @param int $orderId
 * @param int|null $adminId
 * @return bool
 * @throws Exception
 */
function restoreStockForOrder(int $orderId, ?int $adminId = null): bool {
    $db = getConnection();
    if (!$db) throw new Exception("Database unavailable");

    $stmtOrd = $db->prepare("SELECT order_number FROM orders WHERE id = :oid");
    $stmtOrd->execute([':oid' => $orderId]);
    $order = $stmtOrd->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception("Order #$orderId not found");

    // Check if stock was previously deducted for this order in inventory_transactions
    $stmtCheckTx = $db->prepare("
        SELECT product_id, SUM(ABS(change_amount)) as total_deducted 
        FROM inventory_transactions 
        WHERE reference_id = :ref AND transaction_type = 'order_deduction'
        GROUP BY product_id
    ");
    $stmtCheckTx->execute([':ref' => $order['order_number']]);
    $deductions = $stmtCheckTx->fetchAll(PDO::FETCH_ASSOC);

    // Also check if already restocked
    $stmtCheckRestock = $db->prepare("
        SELECT COUNT(*) FROM inventory_transactions 
        WHERE reference_id = :ref AND transaction_type = 'cancellation_restock'
    ");
    $stmtCheckRestock->execute([':ref' => $order['order_number']]);
    $alreadyRestocked = (int)$stmtCheckRestock->fetchColumn() > 0;

    if (empty($deductions) || $alreadyRestocked) {
        // No previously deducted stock to restore or already restored
        return false;
    }

    $db->beginTransaction();
    try {
        $updateInv = $db->prepare("UPDATE inventory SET current_stock = current_stock + :qty WHERE product_id = :pid");
        $logTx = $db->prepare("
            INSERT INTO inventory_transactions (product_id, change_amount, transaction_type, reference_id, notes, created_by)
            VALUES (:pid, :delta, 'cancellation_restock', :ref, :notes, :admin_id)
        ");

        foreach ($deductions as $d) {
            $qty = (int)$d['total_deducted'];
            $pid = $d['product_id'];

            $updateInv->execute([':qty' => $qty, ':pid' => $pid]);
            $logTx->execute([
                ':pid' => $pid,
                ':delta' => $qty,
                ':ref' => $order['order_number'],
                ':notes' => "Reversed deduction following order cancellation/refund",
                ':admin_id' => $adminId
            ]);
        }

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Adjust stock manually with audit note
 * @param string $productId
 * @param int $delta (positive or negative)
 * @param string $reason
 * @param int|null $adminId
 * @return bool
 * @throws Exception
 */
function adjustInventoryStock(string $productId, int $delta, string $reason, ?int $adminId = null): bool {
    $db = getConnection();
    if (!$db) throw new Exception("Database unavailable");

    // Fetch current stock
    $stmt = $db->prepare("SELECT current_stock FROM inventory WHERE product_id = :pid");
    $stmt->execute([':pid' => $productId]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        throw new Exception("No inventory record found for product $productId");
    }

    $newStock = (int)$current + $delta;
    if ($newStock < 0) {
        throw new Exception("Cannot reduce stock below zero. Current stock is $current.");
    }

    $db->beginTransaction();
    try {
        $upd = $db->prepare("UPDATE inventory SET current_stock = :new_stock WHERE product_id = :pid");
        $upd->execute([':new_stock' => $newStock, ':pid' => $productId]);

        $log = $db->prepare("
            INSERT INTO inventory_transactions (product_id, change_amount, transaction_type, reference_id, notes, created_by)
            VALUES (:pid, :delta, 'manual_adjustment', :ref, :notes, :admin_id)
        ");
        $log->execute([
            ':pid' => $productId,
            ':delta' => $delta,
            ':ref' => 'MANUAL-ADJ',
            ':notes' => $reason,
            ':admin_id' => $adminId
        ]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Get low stock alerts
 * @return array
 */
function getLowStockAlerts(): array {
    $db = getConnection();
    if (!$db) return [];

    $sql = "
        SELECT 
            p.name AS product_name,
            p.image,
            i.sku,
            i.current_stock,
            i.min_stock_level,
            i.reorder_point,
            i.max_stock_level,
            i.last_updated
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE i.current_stock <= i.reorder_point
        ORDER BY i.current_stock ASC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
