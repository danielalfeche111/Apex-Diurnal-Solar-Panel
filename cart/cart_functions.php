<?php
// cart_functions.php – central cart logic, uses PHP sessions
require_once __DIR__ . '/../auth.php';

// Ensure cart array exists in session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/**
 * Get Cart domain service instance for authenticated sessions
 */
function get_cart_service(): ?Cart {
    static $cartService = null;
    if ($cartService !== null) {
        return $cartService;
    }
    if (isLoggedIn()) {
        require_once __DIR__ . '/../config.php';
        require_once __DIR__ . '/../Cart.php';
        try {
            $db = getConnection();
            if ($db) {
                $cartService = new Cart($db);
                return $cartService;
            }
        } catch (Exception $e) {
            error_log('[cart_functions] Error initializing Cart: ' . $e->getMessage());
        }
    }
    return null;
}

/**
 * Add a product to the cart (or increase quantity)
 */
function add_to_cart(string $productId, int $qty = 1): void {
    if (!isLoggedIn()) {
        return;
    }
    if ($qty < 1) {
        $qty = 1;
    }

    $cart = get_cart_service();
    $userId = getCurrentUserId();
    if ($cart && $userId) {
        try {
            $cart->addItem($userId, $productId, $qty);
            return;
        } catch (Exception $e) {
            error_log('[cart_functions] Error adding item to database cart: ' . $e->getMessage());
        }
    }
}

/**
 * Remove a product from the cart completely
 */
function remove_from_cart(string $productId): void {
    $cart = get_cart_service();
    $userId = getCurrentUserId();
    if ($cart && $userId) {
        try {
            $cart->removeItem($userId, $productId);
            return;
        } catch (Exception $e) {
            error_log('[cart_functions] Error removing item from database cart: ' . $e->getMessage());
        }
    }

    unset($_SESSION['cart'][$productId]);
}

/**
 * Update quantity of a product (set to $qty, remove if $qty <= 0)
 */
function update_cart(string $productId, int $qty): void {
    if ($qty <= 0) {
        remove_from_cart($productId);
        return;
    }

    $cart = get_cart_service();
    $userId = getCurrentUserId();
    if ($cart && $userId) {
        try {
            $cart->updateQuantity($userId, $productId, $qty);
            return;
        } catch (Exception $e) {
            error_log('[cart_functions] Error updating item in database cart: ' . $e->getMessage());
        }
    }

    $_SESSION['cart'][$productId] = $qty;
}

/**
 * Empty the entire cart
 */
function clear_cart(): void {
    $cart = get_cart_service();
    $userId = getCurrentUserId();
    if ($cart && $userId) {
        try {
            $cart->clearCart($userId);
            return;
        } catch (Exception $e) {
            error_log('[cart_functions] Error clearing database cart: ' . $e->getMessage());
        }
    }

    $_SESSION['cart'] = [];
}

/**
 * Return total distinct item count (for badge)
 */
function cart_item_count(): int {
    return array_sum($_SESSION['cart'] ?? []);
}

/**
 * Build id => product lookup from catalog (handles both numeric and associative catalogs)
 */
function catalog_lookup(array $catalog): array {
    $map = [];
    foreach ($catalog as $key => $product) {
        if (isset($product['id'])) {
            $map[$product['id']] = $product;
        }
        // also keep direct key for associative catalogs
        if (is_string($key) && !isset($map[$key]) && isset($product['title'])) {
            $map[$key] = $product;
        }
    }
    return $map;
}

/**
 * Return grand total price (float) based on product catalog
 */
function cart_total(array $catalog): float {
    $lookup = catalog_lookup($catalog);
    $total = 0.0;
    foreach ($_SESSION['cart'] as $id => $qty) {
        if (isset($lookup[$id])) {
            $price = floatval(str_replace(['$', '₱', '&#8369;', 'PHP', 'php', ','], '', $lookup[$id]['price']));
            $total += $price * $qty;
        }
    }
    return $total;
}

/**
 * Merge cart session data with product catalog for display
 */
function get_cart_items(array $catalog): array {
    $lookup = catalog_lookup($catalog);
    $items = [];
    foreach ($_SESSION['cart'] as $id => $qty) {
        if (isset($lookup[$id])) {
            $product = $lookup[$id];
            $product['quantity'] = $qty;
            $price = floatval(str_replace(['$', '₱', '&#8369;', 'PHP', 'php', ','], '', $product['price']));
            $product['line_total'] = $price * $qty;
            $items[$id] = $product;
        }
    }
    return $items;
}
?>
