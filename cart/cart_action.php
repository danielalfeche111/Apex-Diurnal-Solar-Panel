<?php
// cart_action.php – handles AJAX requests for cart operations
require_once __DIR__ . '/../product_data.php';
require_once __DIR__ . '/cart_functions.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

// Require login for cart operations
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$action = $_POST['action'] ?? '';
$productId = $_POST['product_id'] ?? '';
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

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
    default:
        // no action
        break;
}

// Prepare response data
$response = [];
$response['itemCount'] = cart_item_count();
$response['grandTotal'] = number_format(cart_total($products), 2);
$response['items'] = [];
foreach (get_cart_items($products) as $id => $item) {
    $response['items'][] = [
        'id' => $id,
        'title' => $item['title'],
        'price' => $item['price'],
        'quantity' => $item['quantity'],
        'line_total' => number_format($item['line_total'], 2),
        'image' => $item['image'],
        'alt' => $item['alt']
    ];
}

echo json_encode($response);
?>
