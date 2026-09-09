<?php
// Process order and sync inventory

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

require_once __DIR__ . '/../inventory/sync.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    header('Location: index.php?err=' . urlencode('Security token expired. Please try again.'));
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');
$adminNote = trim($_POST['admin_note'] ?? '');
$redirectSource = trim($_POST['redirect_source'] ?? 'index');

$admin = getAdminUser();
$adminId = $admin['id'] ?? null;

$allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
    $redirectUrl = ($redirectSource === 'view') ? "view.php?id=$orderId&err=" . urlencode('Invalid order or status.') : "index.php?err=" . urlencode('Invalid order or status.');
    header("Location: $redirectUrl");
    exit;
}

try {
    $db = getConnection();
    
    // Fetch current order
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order #$orderId was not found.");
    }

    $currentStatus = $order['status'];

    // Return if status unchanged
    if ($currentStatus === $newStatus) {
        $dest = ($redirectSource === 'view') ? "view.php?id=$orderId&msg=" . urlencode('No change in status.') : "index.php?msg=" . urlencode('No change in status.');
        header("Location: $dest");
        exit;
    }

    // Deduct stock for active order
    if ($currentStatus === 'pending' && in_array($newStatus, ['processing', 'shipped', 'delivered'])) {
        deductStockForOrder($orderId, $adminId);
    }

    // Restore stock for cancelled order
    if (in_array($currentStatus, ['processing', 'shipped', 'delivered']) && in_array($newStatus, ['cancelled', 'refunded'])) {
        restoreStockForOrder($orderId, $adminId);
    }

    // Append notes if provided
    $updatedNotes = $order['notes'];
    if (!empty($adminNote)) {
        $timestamp = date('Y-m-d H:i');
        $updatedNotes .= "\n[" . $timestamp . " " . ($admin['username'] ?? 'Admin') . "]: " . $adminNote;
    }

    // Set admin id in session
    if ($adminId) {
        $db->exec("SET @current_admin_id = " . (int)$adminId);
    }

    // Update order status
    $stmtUpdate = $db->prepare("UPDATE orders SET status = :st, notes = :nt WHERE id = :id");
    $stmtUpdate->execute([
        ':st' => $newStatus,
        ':nt' => $updatedNotes,
        ':id' => $orderId
    ]);

    $msg = "Order {$order['order_number']} status updated to " . ucfirst($newStatus) . ".";
    $dest = ($redirectSource === 'view') ? "view.php?id=$orderId&msg=" . urlencode($msg) : "index.php?msg=" . urlencode($msg);
    header("Location: $dest");
    exit;

} catch (Exception $e) {
    $errMsg = $e->getMessage();
    $dest = ($redirectSource === 'view') ? "view.php?id=$orderId&err=" . urlencode($errMsg) : "index.php?err=" . urlencode($errMsg);
    header("Location: $dest");
    exit;
}
