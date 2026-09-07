<?php
/**
 * admin/quotes/respond.php - Quote Response & Status Handler
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
$status = trim($_POST['status'] ?? 'quoted');
$quotedAmount = !empty($_POST['quoted_amount']) ? (float)$_POST['quoted_amount'] : null;
$adminNotes = trim($_POST['admin_notes'] ?? '');

$admin = getAdminUser();
$adminId = $admin['id'] ?? null;

if ($quoteId <= 0) {
    header('Location: index.php?err=' . urlencode('Invalid quote reference.'));
    exit;
}

try {
    $db = (new Database())->getConnection();

    $sql = "
        UPDATE quote_requests 
        SET status = :st, 
            quoted_amount = :amt, 
            admin_notes = :notes, 
            quoted_by = :quoted_by, 
            quoted_at = NOW() 
        WHERE id = :id
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':st' => $status,
        ':amt' => $quotedAmount,
        ':notes' => $adminNotes,
        ':quoted_by' => $adminId,
        ':id' => $quoteId
    ]);

    header("Location: view.php?id=$quoteId&msg=" . urlencode("Quote successfully updated to " . ucfirst($status) . "."));
    exit;
} catch (Exception $e) {
    header("Location: view.php?id=$quoteId&err=" . urlencode($e->getMessage()));
    exit;
}
