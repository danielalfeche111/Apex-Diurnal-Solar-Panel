<?php
/**
 * account/orders/action.php
 * Handles customer-side confirmation or cancellation of commercial installation requests
 */

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$action  = trim($_POST['action'] ?? '');
$userId  = getCurrentUserId();

if ($orderId <= 0 || !in_array($action, ['confirm', 'cancel'], true)) {
    header('Location: index.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verify order ownership
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmt->execute([':id' => $orderId, ':uid' => $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Location: index.php?err=' . urlencode('Order not found or access denied.'));
        exit;
    }

    // Extract quote number if this order was generated from an RFQ
    $quoteNum = null;
    if (preg_match('/RFQ-\d{8}-\d{4}/', $order['order_number'], $matches)) {
        $quoteNum = $matches[0];
    } elseif (preg_match('/RFQ-\d{8}-\d{4}/', $order['notes'] ?? '', $matches)) {
        $quoteNum = $matches[0];
    }

    $db->beginTransaction();

    if ($action === 'confirm') {
        if ($order['status'] !== 'pending') {
            $db->rollBack();
            header('Location: view.php?id=' . $orderId . '&err=' . urlencode('This order cannot be confirmed at this stage.'));
            exit;
        }

        // 1. Update order status to client_confirmed
        $updOrder = $db->prepare("UPDATE orders SET status = 'client_confirmed', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $updOrder->execute([':id' => $orderId]);

        // 2. Update quote_requests status to client_confirmed
        if ($quoteNum) {
            $updQuote = $db->prepare("UPDATE quote_requests SET status = 'client_confirmed', updated_at = CURRENT_TIMESTAMP WHERE quote_number = :qnum");
            $updQuote->execute([':qnum' => $quoteNum]);
        }

        $db->commit();
        $msg = 'Commercial grid installation successfully confirmed! Awaiting admin approval and head engineer assignment.';
        header('Location: view.php?id=' . $orderId . '&msg=' . urlencode($msg));
        exit;

    } elseif ($action === 'cancel') {
        if (!in_array($order['status'], ['pending', 'client_confirmed'], true)) {
            $db->rollBack();
            header('Location: view.php?id=' . $orderId . '&err=' . urlencode('This order can no longer be cancelled.'));
            exit;
        }

        // 1. Update order status to cancelled
        $updOrder = $db->prepare("UPDATE orders SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $updOrder->execute([':id' => $orderId]);

        // 2. Update quote_requests status to cancelled
        if ($quoteNum) {
            $updQuote = $db->prepare("UPDATE quote_requests SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE quote_number = :qnum");
            $updQuote->execute([':qnum' => $quoteNum]);

            // Also cancel service booking if one exists
            $bref = 'INST-' . $quoteNum;
            $updBooking = $db->prepare("UPDATE service_bookings SET status = 'cancelled' WHERE booking_reference = :bref");
            $updBooking->execute([':bref' => $bref]);
        }

        $db->commit();
        $msg = 'Commercial installation request has been cancelled.';
        header('Location: view.php?id=' . $orderId . '&msg=' . urlencode($msg));
        exit;
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    header('Location: view.php?id=' . $orderId . '&err=' . urlencode('An error occurred: ' . $e->getMessage()));
    exit;
}
