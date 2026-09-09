<?php
/**
 * admin/quotes/respond.php - Commercial Installation Confirmation & Head Assignment Handler
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
$status = trim($_POST['status'] ?? 'confirmed');
$installationHead = trim($_POST['installation_head'] ?? '');
$installationDate = trim($_POST['installation_date'] ?? '');
$adminNotes = trim($_POST['admin_notes'] ?? '');

// If the admin assigned an installation head, ensure status is confirmed (unless explicitly cancelled)
if (!empty($installationHead) && $status !== 'cancelled') {
    $status = 'confirmed';
}

$admin = getAdminUser();
$adminId = $admin['id'] ?? null;

if ($quoteId <= 0) {
    header('Location: index.php?err=' . urlencode('Invalid quote reference.'));
    exit;
}

try {
    $db = getConnection();

    // Fetch existing quote details
    $curStmt = $db->prepare("SELECT * FROM quote_requests WHERE id = :id");
    $curStmt->execute([':id' => $quoteId]);
    $quote = $curStmt->fetch(PDO::FETCH_ASSOC);

    if (!$quote) {
        throw new Exception("Quote request #$quoteId was not found.");
    }

    $db->beginTransaction();

    // 1. Update quote_requests with confirmation details and assigned head
    $sql = "
        UPDATE quote_requests 
        SET status = :st, 
            installation_head = :head, 
            installation_date = :idate, 
            admin_notes = :notes, 
            quoted_by = :quoted_by, 
            quoted_at = NOW() 
        WHERE id = :id
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':st' => $status,
        ':head' => !empty($installationHead) ? $installationHead : null,
        ':idate' => !empty($installationDate) ? $installationDate : null,
        ':notes' => $adminNotes,
        ':quoted_by' => $adminId,
        ':id' => $quoteId
    ]);

    // 2. Synchronize with service_bookings for calendar integration
    $bookingRef = 'INSTALL-' . $quote['quote_number'];
    $checkBooking = $db->prepare("SELECT id FROM service_bookings WHERE booking_reference = :bref");
    $checkBooking->execute([':bref' => $bookingRef]);
    $existingBooking = $checkBooking->fetch(PDO::FETCH_ASSOC);

    $bookingStatus = 'pending';
    if ($status === 'confirmed' || $status === 'in_progress') {
        $bookingStatus = 'confirmed';
    } elseif ($status === 'completed') {
        $bookingStatus = 'completed';
    } elseif ($status === 'cancelled') {
        $bookingStatus = 'cancelled';
    }

    $effectiveDate = !empty($installationDate) ? $installationDate : date('Y-m-d');
    $effectiveNotes = "Commercial Grid Solar Installation\nCompany: {$quote['company_name']}\nFacility: {$quote['facility_size']} sqm\n" . $adminNotes;

    if ($existingBooking) {
        $updateBooking = $db->prepare("
            UPDATE service_bookings 
            SET assigned_technician = :tech, 
                preferred_date = :pdate, 
                status = :st, 
                access_notes = :notes 
            WHERE id = :id
        ");
        $updateBooking->execute([
            ':tech' => !empty($installationHead) ? $installationHead : 'Unassigned',
            ':pdate' => $effectiveDate,
            ':st' => $bookingStatus,
            ':notes' => $effectiveNotes,
            ':id' => $existingBooking['id']
        ]);
    } elseif ($status === 'confirmed' || !empty($installationHead)) {
        $insertBooking = $db->prepare("
            INSERT INTO service_bookings (
                booking_reference, customer_name, customer_email, customer_phone,
                service_type, preferred_date, preferred_time_slot, status,
                address, access_notes, assigned_technician
            ) VALUES (
                :bref, :cname, :email, :phone,
                'installation', :pdate, 'morning', :st,
                :addr, :notes, :tech
            )
        ");
        $insertBooking->execute([
            ':bref' => $bookingRef,
            ':cname' => $quote['company_name'] . ' (' . $quote['contact_person'] . ')',
            ':email' => $quote['email'],
            ':phone' => $quote['phone'],
            ':pdate' => $effectiveDate,
            ':st' => $bookingStatus,
            ':addr' => $quote['installation_address'] ?? 'Project site address',
            ':notes' => $effectiveNotes,
            ':tech' => !empty($installationHead) ? $installationHead : 'Unassigned'
        ]);
    }

    // 3. Synchronize with orders so the user's "My Orders" displays it as already confirmed
    $orderUserId = !empty($quote['user_id']) ? (int)$quote['user_id'] : null;
    if (!$orderUserId && !empty($quote['email'])) {
        $userStmt = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $userStmt->execute([':email' => $quote['email']]);
        $matchedUser = $userStmt->fetch(PDO::FETCH_ASSOC);
        $orderUserId = $matchedUser ? (int)$matchedUser['id'] : null;
    }

    $orderRef = 'APD-INST-' . $quote['quote_number'];
    $checkOrder = $db->prepare("SELECT id, notes FROM orders WHERE order_number = :ordNum OR notes LIKE :qsearch LIMIT 1");
    $checkOrder->execute([
        ':ordNum' => $orderRef,
        ':qsearch' => '%' . $quote['quote_number'] . '%'
    ]);
    $existingOrder = $checkOrder->fetch(PDO::FETCH_ASSOC);

    if ($status === 'confirmed') {
        $orderTotal = (float)($quote['quoted_amount'] ?? $quote['estimated_installation_cost'] ?? 0.00);
        $orderSubtotal = round($orderTotal / 1.12, 2);
        $orderTax = $orderTotal - $orderSubtotal;
        $orderNotes = "Confirmed Commercial Grid Solar Installation\nRFQ: {$quote['quote_number']}\nFacility: {$quote['facility_size']} sqm\nAssigned Head: " . (!empty($installationHead) ? $installationHead : 'Unassigned') . "\nScheduled Date: " . (!empty($installationDate) ? $installationDate : 'TBD') . "\n" . $adminNotes;

        if ($existingOrder) {
            $origNotes = $existingOrder['notes'] ?? '';
            $effectiveNotes = $orderNotes;
            if (strpos($origNotes, 'Estimated System Capacity') !== false && strpos($orderNotes, 'Estimated System Capacity') === false) {
                $effectiveNotes = $orderNotes . "\n\n" . $origNotes;
            }

            $updOrder = $db->prepare("
                UPDATE orders 
                SET status = 'confirmed',
                    user_id = COALESCE(:uid, user_id),
                    installation_head = :head,
                    installation_date = :idate,
                    notes = :notes,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $updOrder->execute([
                ':uid' => $orderUserId,
                ':head' => !empty($installationHead) ? $installationHead : null,
                ':idate' => !empty($installationDate) ? $installationDate : null,
                ':notes' => $effectiveNotes,
                ':id' => $existingOrder['id']
            ]);

            $histStmt = $db->prepare("INSERT INTO order_status_history (order_id, status, notes) VALUES (:oid, 'confirmed', :notes)");
            $histStmt->execute([
                ':oid' => $existingOrder['id'],
                ':notes' => 'Commercial grid installation confirmed by admin & head assigned: ' . (!empty($installationHead) ? $installationHead : 'Unassigned')
            ]);
        } else {
            $insOrder = $db->prepare("
                INSERT INTO orders (
                    order_number, user_id, customer_name, customer_email, customer_phone,
                    property_type, payment_method, status, subtotal, tax_amount, total_amount,
                    shipping_address, notes, installation_head, installation_date
                ) VALUES (
                    :ord_num, :uid, :name, :email, :phone,
                    'Commercial', 'Commercial Installation Contract', 'confirmed', :subtotal, :tax, :total,
                    :addr, :notes, :head, :idate
                )
            ");
            $insOrder->execute([
                ':ord_num' => $orderRef,
                ':uid' => $orderUserId,
                ':name' => $quote['company_name'] . ' (' . $quote['contact_person'] . ')',
                ':email' => $quote['email'],
                ':phone' => $quote['phone'],
                ':subtotal' => $orderSubtotal,
                ':tax' => $orderTax,
                ':total' => $orderTotal,
                ':addr' => $quote['installation_address'] ?? 'Project site address',
                ':notes' => $orderNotes,
                ':head' => !empty($installationHead) ? $installationHead : null,
                ':idate' => !empty($installationDate) ? $installationDate : null
            ]);
            $newOrderId = (int)$db->lastInsertId();

            $insItem = $db->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price)
                VALUES (:oid, 'commercial-grids', :pname, 1, :uprice, :tprice)
            ");
            $productLabel = "Commercial Grid Turnkey Solar Installation - " . $quote['company_name'];
            $insItem->execute([
                ':oid' => $newOrderId,
                ':pname' => $productLabel,
                ':uprice' => $orderSubtotal,
                ':tprice' => $orderSubtotal
            ]);

            $histStmt = $db->prepare("INSERT INTO order_status_history (order_id, status, notes) VALUES (:oid, 'confirmed', :notes)");
            $histStmt->execute([
                ':oid' => $newOrderId,
                ':notes' => 'Commercial grid installation confirmed by admin & head assigned: ' . (!empty($installationHead) ? $installationHead : 'Unassigned')
            ]);
        }
    } elseif ($existingOrder && $status === 'cancelled') {
        $updOrder = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id");
        $updOrder->execute([':id' => $existingOrder['id']]);
    }

    $db->commit();

    $headMsg = !empty($installationHead) ? " and assigned to $installationHead" : "";
    $msgText = ($status === 'confirmed')
        ? "Commercial installation request successfully confirmed$headMsg."
        : "Commercial installation request updated to " . ucfirst($status) . "$headMsg.";

    header("Location: view.php?id=$quoteId&msg=" . urlencode($msgText));
    exit;
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    header("Location: view.php?id=$quoteId&err=" . urlencode($e->getMessage()));
    exit;
}

