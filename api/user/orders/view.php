<?php
/**
 * api/user/orders/view.php
 * Authenticated User Order Detail & Status Tracker REST API
 * 
 * Query Parameters:
 * - id (int, required): Order ID
 */

require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required to view order details.'
    ]);
    exit;
}

$userId = getCurrentUserId();
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Valid order ID is required.'
    ]);
    exit;
}

try {
    $db = getConnection();
    if (!$db) {
        throw new Exception('Database connection unavailable.');
    }

    // 1. Fetch order with strict user ownership validation
    $orderSql = "
        SELECT 
            id,
            order_number,
            user_id,
            customer_name,
            customer_email,
            customer_phone,
            property_type,
            payment_method,
            status,
            subtotal,
            tax_amount,
            total_amount,
            shipping_address,
            billing_address,
            notes,
            created_at,
            updated_at
        FROM orders
        WHERE id = :id AND user_id = :user_id
        LIMIT 1
    ";
    $orderStmt = $db->prepare($orderSql);
    $orderStmt->execute([
        ':id'      => $orderId,
        ':user_id' => $userId
    ]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error'   => 'Order not found or access denied.'
        ]);
        exit;
    }

    // 2. Fetch order items
    $itemsSql = "
        SELECT 
            oi.id,
            oi.product_id,
            oi.product_name,
            oi.quantity,
            oi.unit_price,
            oi.total_price,
            p.image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :order_id
        ORDER BY oi.id ASC
    ";
    $itemsStmt = $db->prepare($itemsSql);
    $itemsStmt->execute([':order_id' => $orderId]);
    $items = [];
    while ($itemRow = $itemsStmt->fetch(PDO::FETCH_ASSOC)) {
        $items[] = [
            'id'           => (int)$itemRow['id'],
            'product_id'   => $itemRow['product_id'],
            'product_name' => $itemRow['product_name'],
            'quantity'     => (int)$itemRow['quantity'],
            'unit_price'   => (float)$itemRow['unit_price'],
            'total_price'  => (float)$itemRow['total_price'],
            'image'        => $itemRow['image'] ?? 'assets/images/residential arrays.png'
        ];
    }

    // 3. Fetch status history timeline
    $historySql = "
        SELECT 
            id,
            status,
            changed_at,
            notes
        FROM order_status_history
        WHERE order_id = :order_id
        ORDER BY changed_at ASC, id ASC
    ";
    $historyStmt = $db->prepare($historySql);
    $historyStmt->execute([':order_id' => $orderId]);
    $history = [];
    while ($hRow = $historyStmt->fetch(PDO::FETCH_ASSOC)) {
        $history[] = [
            'id'         => (int)$hRow['id'],
            'status'     => $hRow['status'],
            'changed_at' => $hRow['changed_at'],
            'notes'      => $hRow['notes']
        ];
    }

    // Fallback: If no history exists, provide current status as initial entry
    if (empty($history)) {
        $history[] = [
            'id'         => 0,
            'status'     => $order['status'],
            'changed_at' => $order['created_at'],
            'notes'      => 'Order placed'
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'order' => [
                'id'               => (int)$order['id'],
                'order_number'     => $order['order_number'],
                'customer_name'    => $order['customer_name'],
                'customer_email'   => $order['customer_email'],
                'customer_phone'   => $order['customer_phone'],
                'property_type'    => $order['property_type'],
                'payment_method'   => $order['payment_method'],
                'status'           => $order['status'],
                'subtotal'         => (float)$order['subtotal'],
                'tax_amount'       => (float)$order['tax_amount'],
                'total_amount'     => (float)$order['total_amount'],
                'shipping_address' => $order['shipping_address'],
                'billing_address'  => $order['billing_address'] ?: $order['shipping_address'],
                'notes'            => $order['notes'],
                'created_at'       => $order['created_at'],
                'items'            => $items
            ],
            'status_history' => $history
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Server error retrieving order: ' . $e->getMessage()
    ]);
}
