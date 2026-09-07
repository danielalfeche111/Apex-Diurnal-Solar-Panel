<?php
/**
 * admin/quotes/convert.php - Convert Accepted Commercial Quote to Official Order
 */

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    header('Location: index.php?err=' . urlencode('Security token expired.'));
    exit;
}

$quoteId = (int)($_POST['quote_id'] ?? 0);

if ($quoteId <= 0) {
    header('Location: index.php?err=' . urlencode('Invalid quote reference.'));
    exit;
}

$db = (new Database())->getConnection();

try {
    $stmt = $db->prepare("SELECT * FROM quote_requests WHERE id = :id");
    $stmt->execute([':id' => $quoteId]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quote) {
        throw new Exception("Quote request #$quoteId was not found.");
    }

    $db->beginTransaction();

    // Generate formal Order Number
    $orderNumber = 'APD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
    
    // Amount
    $totalAmount = (float)($quote['quoted_amount'] ?? $quote['estimated_installation_cost'] ?? 15000.00);
    $subtotal = round($totalAmount / 1.08, 2);
    $tax = $totalAmount - $subtotal;

    // 1. Insert into orders
    $insertOrder = $db->prepare("
        INSERT INTO orders (
            order_number, user_id, customer_name, customer_email, customer_phone,
            property_type, payment_method, status, subtotal, tax_amount, total_amount,
            shipping_address, notes
        ) VALUES (
            :ord_num, NULL, :name, :email, :phone,
            'Commercial', 'Direct Commercial Wire / Invoiced', 'processing', :subtotal, :tax, :total,
            :addr, :notes
        )
    ");

    $notes = "Converted from Commercial RFQ #{$quote['quote_number']}.\nFacility: {$quote['facility_type']} ({$quote['facility_size']} sqm).\nEst. System Size: {$quote['estimated_system_size']} kW.";

    $insertOrder->execute([
        ':ord_num' => $orderNumber,
        ':name' => $quote['company_name'] . ' (' . $quote['contact_person'] . ')',
        ':email' => $quote['email'],
        ':phone' => $quote['phone'],
        ':subtotal' => $subtotal,
        ':tax' => $tax,
        ':total' => $totalAmount,
        ':addr' => $quote['installation_address'] ?? 'Site address as per commercial proposal',
        ':notes' => $notes
    ]);

    $newOrderId = (int)$db->lastInsertId();

    // 2. Insert line item
    $insertItem = $db->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price)
        VALUES (:oid, 'commercial-grids', :pname, 1, :price, :price)
    ");
    $productLabel = "Commercial Grid Turnkey Solar Project - " . ($quote['estimated_system_size'] ? $quote['estimated_system_size'] . ' kW' : 'Custom Array');
    $insertItem->execute([
        ':oid' => $newOrderId,
        ':pname' => $productLabel,
        ':price' => $subtotal
    ]);

    // 3. Mark quote as accepted
    $updQuote = $db->prepare("UPDATE quote_requests SET status = 'accepted' WHERE id = :id");
    $updQuote->execute([':id' => $quoteId]);

    $db->commit();

    header("Location: ../orders/view.php?id=$newOrderId&msg=" . urlencode("Quote {$quote['quote_number']} successfully converted to Order $orderNumber."));
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    header("Location: view.php?id=$quoteId&err=" . urlencode($e->getMessage()));
    exit;
}
