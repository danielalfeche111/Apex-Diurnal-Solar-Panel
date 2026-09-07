<?php
/**
 * admin/orders/api.php - AJAX API for Order Management
 */

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../inventory/sync.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$db = (new Database())->getConnection();

if ($action === 'check_stock') {
    $orderId = (int)($_GET['order_id'] ?? 0);
    $result = checkOrderStockAvailability($orderId);
    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}

if ($action === 'quick_update_status') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');
    $admin = getAdminUser();
    $adminId = $admin['id'] ?? null;

    try {
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id");
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception("Order not found");
        }

        $currentStatus = $order['status'];

        if ($currentStatus === 'pending' && in_array($newStatus, ['processing', 'shipped', 'delivered'])) {
            deductStockForOrder($orderId, $adminId);
        }

        if (in_array($currentStatus, ['processing', 'shipped', 'delivered']) && in_array($newStatus, ['cancelled', 'refunded'])) {
            restoreStockForOrder($orderId, $adminId);
        }

        $upd = $db->prepare("UPDATE orders SET status = :st WHERE id = :id");
        $upd->execute([':st' => $newStatus, ':id' => $orderId]);

        echo json_encode([
            'success' => true,
            'message' => "Order {$order['order_number']} status updated to " . ucfirst($newStatus),
            'new_status' => $newStatus
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Unknown endpoint action']);
