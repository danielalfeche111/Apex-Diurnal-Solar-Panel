<?php
// cart_action.php – handles AJAX requests for cart operations
require_once __DIR__ . '/../product_data.php';
require_once __DIR__ . '/cart_functions.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$productId = $_POST['product_id'] ?? ($_GET['product_id'] ?? '');
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : (isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1);
$notices = [];

// If user is authenticated, ensure their database cart is hydrated
if (isLoggedIn()) {
    $cart = get_cart_service();
    $userId = getCurrentUserId();
    if ($cart && $userId) {
        try {
            // First hydrate/validate to pick up any stock or price adjustments
            $hydration = $cart->validateAndHydrate($userId);
            if (!empty($hydration['notices'])) {
                $notices = array_merge($notices, $hydration['notices']);
            }
        } catch (Exception $e) {
            error_log('[cart_action] Hydration error: ' . $e->getMessage());
        }
    }
}

switch ($action) {
    case 'add':
        if ($productId) {
            add_to_cart($productId, $quantity);
        }
        break;
    case 'update':
        if ($productId) {
            update_cart($productId, $quantity);
        }
        break;
    case 'remove':
        if ($productId) {
            remove_from_cart($productId);
        }
        break;
    case 'clear':
        clear_cart();
        break;
    case 'get':
    case 'fetch':
    default:
        // Return current state without modifications
        break;
}

// Prepare response data
$response = [
    'success'         => true,
    'isAuthenticated' => isLoggedIn(),
    'itemCount'       => cart_item_count(),
    'grandTotal'      => number_format(cart_total($products), 2),
    'items'           => [],
    'notices'         => $notices
];

foreach (get_cart_items($products) as $id => $item) {
    $response['items'][] = [
        'id'         => $id,
        'title'      => $item['title'],
        'price'      => $item['price'],
        'quantity'   => $item['quantity'],
        'line_total' => number_format($item['line_total'], 2),
        'image'      => $item['image'],
        'alt'        => $item['alt']
    ];
}

echo json_encode($response);
exit;
