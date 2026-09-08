<?php
/**
 * api/user/orders/list.php
 * Authenticated User Order History List REST API
 * 
 * Query Parameters:
 * - page (int, default: 1)
 * - limit (int, default: 10, max: 50)
 * - sort_by (order_number, created_at, total_amount, status; default: created_at)
 * - sort_dir (asc, desc; default: desc)
 * - status (pending, processing, shipped, delivered, cancelled, refunded; optional)
 * - start_date (YYYY-MM-DD, optional)
 * - end_date (YYYY-MM-DD, optional)
 */

require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../db.php';

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
        'error' => 'Authentication required to view order history.'
    ]);
    exit;
}

$userId = getCurrentUserId();

try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        throw new Exception('Database connection unavailable.');
    }

    // Input parsing & sanitization
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $validSortColumns = [
        'order_number' => 'o.order_number',
        'created_at'   => 'o.created_at',
        'total_amount' => 'o.total_amount',
        'status'       => 'o.status'
    ];
    $sortByParam = strtolower(trim($_GET['sort_by'] ?? 'created_at'));
    $sortCol = $validSortColumns[$sortByParam] ?? 'o.created_at';

    $sortDirParam = strtolower(trim($_GET['sort_dir'] ?? 'desc'));
    $sortDir = ($sortDirParam === 'asc') ? 'ASC' : 'DESC';

    // Filters
    $userEmail = getCurrentUserEmail() ?? '';
    if (!empty($userEmail)) {
        $db->prepare("UPDATE orders SET user_id = :uid WHERE user_id IS NULL AND LOWER(customer_email) = LOWER(:uemail)")->execute([':uid' => $userId, ':uemail' => $userEmail]);
    }

    $whereClauses = ['o.user_id = :user_id'];
    $params = [':user_id' => $userId];

    $validStatuses = ['pending', 'client_confirmed', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    if (!empty($_GET['status'])) {
        $filterStatus = strtolower(trim($_GET['status']));
        if (in_array($filterStatus, $validStatuses, true)) {
            $whereClauses[] = 'o.status = :status';
            $params[':status'] = $filterStatus;
        }
    }

    if (!empty($_GET['start_date'])) {
        $startDate = trim($_GET['start_date']);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $whereClauses[] = 'o.created_at >= :start_date';
            $params[':start_date'] = $startDate . ' 00:00:00';
        }
    }

    if (!empty($_GET['end_date'])) {
        $endDate = trim($_GET['end_date']);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $whereClauses[] = 'o.created_at <= :end_date';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
    }

    $whereSql = implode(' AND ', $whereClauses);

    // 1. Total count query
    $countSql = "SELECT COUNT(*) FROM orders o WHERE {$whereSql}";
    $countStmt = $db->prepare($countSql);
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val);
    }
    $countStmt->execute();
    $totalOrders = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalOrders / $limit);

    // 2. Orders list query
    $listSql = "
        SELECT 
            o.id,
            o.order_number,
            o.created_at,
            o.status,
            o.property_type,
            o.total_amount,
            COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = o.id), 0) AS item_count
        FROM orders o
        WHERE {$whereSql}
        ORDER BY {$sortCol} {$sortDir}, o.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $listStmt = $db->prepare($listSql);
    foreach ($params as $key => $val) {
        $listStmt->bindValue($key, $val);
    }
    $listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $listStmt->execute();

    $orders = [];
    while ($row = $listStmt->fetch(PDO::FETCH_ASSOC)) {
        $orders[] = [
            'id'            => (int)$row['id'],
            'order_number'  => $row['order_number'],
            'created_at'    => $row['created_at'],
            'status'        => $row['status'],
            'property_type' => $row['property_type'] ?? 'Residential',
            'total_amount'  => (float)$row['total_amount'],
            'item_count'    => (int)$row['item_count']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'orders'     => $orders,
            'pagination' => [
                'total' => $totalOrders,
                'page'  => $page,
                'limit' => $limit,
                'pages' => max(1, $totalPages)
            ]
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Server error retrieving orders: ' . $e->getMessage()
    ]);
}
