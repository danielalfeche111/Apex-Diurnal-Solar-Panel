<?php
/**
 * admin/inventory/edit.php - Stock Adjustment Processor
 */

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

require_once __DIR__ . '/sync.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    header('Location: index.php?err=' . urlencode('Security token expired. Please try again.'));
    exit;
}

$productId = trim($_POST['product_id'] ?? '');
$adjType = trim($_POST['adjustment_type'] ?? 'add');
$quantity = (int)($_POST['quantity'] ?? 0);
$reason = trim($_POST['reason'] ?? 'Manual stock adjustment');
$admin = getAdminUser();
$adminId = $admin['id'] ?? null;

if (empty($productId) || $quantity <= 0) {
    header('Location: index.php?err=' . urlencode('Invalid quantity or product specified.'));
    exit;
}

$delta = ($adjType === 'add') ? $quantity : -$quantity;

try {
    adjustInventoryStock($productId, $delta, $reason, $adminId);
    $msg = sprintf("Stock successfully adjusted by %s%d units.", ($delta > 0 ? '+' : ''), $delta);
    header('Location: index.php?msg=' . urlencode($msg));
    exit;
} catch (Exception $e) {
    header('Location: index.php?err=' . urlencode($e->getMessage()));
    exit;
}
