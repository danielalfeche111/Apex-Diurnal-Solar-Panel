<?php
/**
 * api/cart.php - Apex Diurnal Persistent Cart REST API
 * 
 * Supports:
 * - GET: Fetch current active cart, validate against stock & catalog pricing, return notices
 * - POST: Add/update items, or merge guest cart payload
 * - DELETE: Remove item or empty entire active cart
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../Cart.php';
require_once __DIR__ . '/../cart/cart_functions.php';
require_once __DIR__ . '/../product_data.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();
$cartService = $db ? new Cart($db) : null;
$userId = getCurrentUserId();
$isAuth = isLoggedIn() && !empty($userId);

// Helper to parse input (both JSON and form-encoded)
function getRequestData(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }
    return $_POST;
}

try {
    switch ($method) {
        case 'GET':
            if ($isAuth && $cartService) {
                $hydration = $cartService->validateAndHydrate($userId);
                echo json_encode([
                    'success'         => true,
                    'isAuthenticated' => true,
                    'itemCount'       => $hydration['itemCount'],
                    'grandTotal'      => $hydration['grandTotal'],
                    'items'           => $hydration['items'],
                    'notices'         => $hydration['notices']
                ]);
            } else {
                // Guest session cart
                $items = [];
                $rawItems = get_cart_items($products);
                foreach ($rawItems as $id => $item) {
                    $items[] = [
                        'id'         => $id,
                        'title'      => $item['title'],
                        'price'      => $item['price'],
                        'quantity'   => $item['quantity'],
                        'line_total' => number_format($item['line_total'], 2),
                        'image'      => $item['image'],
                        'alt'        => $item['alt']
                    ];
                }
                echo json_encode([
                    'success'         => true,
                    'isAuthenticated' => false,
                    'itemCount'       => cart_item_count(),
                    'grandTotal'      => number_format(cart_total($products), 2),
                    'items'           => $items,
                    'notices'         => []
                ]);
            }
            break;

        case 'POST':
            $data = getRequestData();
            $action = $data['action'] ?? 'add';
            $productId = $data['product_id'] ?? '';
            $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;

            if ($action === 'merge' && isset($data['items']) && is_array($data['items'])) {
                if ($isAuth && $cartService) {
                    $result = $cartService->mergeSessionCart($userId, $data['items']);
                    echo json_encode(array_merge(['success' => true, 'isAuthenticated' => true], $result));
                } else {
                    foreach ($data['items'] as $pid => $qty) {
                        add_to_cart($pid, (int)$qty);
                    }
                    echo json_encode([
                        'success'         => true,
                        'isAuthenticated' => false,
                        'itemCount'       => cart_item_count(),
                        'grandTotal'      => number_format(cart_total($products), 2),
                        'items'           => array_values(get_cart_items($products)),
                        'notices'         => []
                    ]);
                }
                break;
            }

            if ($action === 'update') {
                if ($productId) {
                    update_cart($productId, $quantity);
                }
            } elseif ($action === 'remove') {
                if ($productId) {
                    remove_from_cart($productId);
                }
            } elseif ($action === 'clear') {
                clear_cart();
            } else {
                // Default 'add'
                if ($productId) {
                    add_to_cart($productId, $quantity);
                }
            }

            // Fetch latest state
            if ($isAuth && $cartService) {
                $summary = $cartService->getCartSummary($userId);
                echo json_encode([
                    'success'         => true,
                    'isAuthenticated' => true,
                    'itemCount'       => $summary['itemCount'],
                    'grandTotal'      => $summary['grandTotal'],
                    'items'           => $summary['items'],
                    'notices'         => $summary['notices']
                ]);
            } else {
                $items = [];
                foreach (get_cart_items($products) as $id => $item) {
                    $items[] = [
                        'id'         => $id,
                        'title'      => $item['title'],
                        'price'      => $item['price'],
                        'quantity'   => $item['quantity'],
                        'line_total' => number_format($item['line_total'], 2),
                        'image'      => $item['image'],
                        'alt'        => $item['alt']
                    ];
                }
                echo json_encode([
                    'success'         => true,
                    'isAuthenticated' => false,
                    'itemCount'       => cart_item_count(),
                    'grandTotal'      => number_format(cart_total($products), 2),
                    'items'           => $items,
                    'notices'         => []
                ]);
            }
            break;

        case 'DELETE':
            // Parse query string or JSON body
            $productId = $_GET['product_id'] ?? '';
            if (empty($productId)) {
                $raw = file_get_contents('php://input');
                $json = json_decode($raw, true);
                if (is_array($json) && !empty($json['product_id'])) {
                    $productId = $json['product_id'];
                }
            }

            if (!empty($productId)) {
                remove_from_cart($productId);
            } else {
                clear_cart();
            }

            if ($isAuth && $cartService) {
                $summary = $cartService->getCartSummary($userId);
                echo json_encode([
                    'success'         => true,
                    'isAuthenticated' => true,
                    'itemCount'       => $summary['itemCount'],
                    'grandTotal'      => $summary['grandTotal'],
                    'items'           => $summary['items'],
                    'notices'         => $summary['notices']
                ]);
            } else {
                echo json_encode([
                    'success'         => true,
                    'isAuthenticated' => false,
                    'itemCount'       => cart_item_count(),
                    'grandTotal'      => number_format(cart_total($products), 2),
                    'items'           => array_values(get_cart_items($products)),
                    'notices'         => []
                ]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
