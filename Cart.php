<?php
/**
 * Cart.php - Apex Diurnal Persistent Cart Model & Service
 * 
 * Manages database-backed shopping cart sessions with:
 * - Direct database persistence for authenticated users (carts & cart_items)
 * - Session hydration and intelligent merging with guest carts
 * - Real-time inventory and pricing validation during rehydration
 * - Conversion lifecycle management upon order checkout
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

class Cart {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        if ($db !== null) {
            $this->db = $db;
        } else {
            $this->db = getConnection();
        }
    }

    /**
     * Get the active cart ID for a user, creating one if not present
     */
    public function getOrCreateActiveCart(int $userId): int {
        $stmt = $this->db->prepare("
            SELECT id FROM carts 
            WHERE user_id = :uid AND status = 'active' 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':uid' => $userId]);
        $cartId = $stmt->fetchColumn();

        if ($cartId) {
            return (int)$cartId;
        }

        $ins = $this->db->prepare("
            INSERT INTO carts (user_id, status) 
            VALUES (:uid, 'active')
        ");
        $ins->execute([':uid' => $userId]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Get all items in the user's active cart with product & inventory details
     */
    public function getItems(int $userId): array {
        $cartId = $this->getOrCreateActiveCart($userId);

        $stmt = $this->db->prepare("
            SELECT 
                ci.id AS item_id,
                ci.cart_id,
                ci.product_id,
                ci.quantity,
                ci.price_at_addition,
                ci.created_at AS item_created_at,
                p.name AS title,
                p.base_price AS current_price,
                p.image,
                p.type AS product_type,
                p.is_active,
                COALESCE(inv.current_stock, 999) AS current_stock
            FROM cart_items ci
            JOIN carts c ON ci.cart_id = c.id
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN inventory inv ON ci.product_id = inv.product_id
            WHERE c.id = :cid AND c.status = 'active'
            ORDER BY ci.id ASC
        ");
        $stmt->execute([':cid' => $cartId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add an item to the user's cart (or increment quantity if already present)
     */
    public function addItem(int $userId, string $productId, int $quantity = 1): array {
        if ($quantity < 1) $quantity = 1;

        $cartId = $this->getOrCreateActiveCart($userId);

        // Fetch current product details
        $stmtProd = $this->db->prepare("SELECT base_price, is_active FROM products WHERE id = :pid LIMIT 1");
        $stmtProd->execute([':pid' => $productId]);
        $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);
        if (!$prod || empty($prod['is_active'])) {
            throw new InvalidArgumentException('Product not found or currently unavailable.');
        }

        $price = (float)$prod['base_price'];

        // Insert or update item
        $stmtItem = $this->db->prepare("
            INSERT INTO cart_items (cart_id, product_id, quantity, price_at_addition)
            VALUES (:cid, :pid, :qty, :price)
            ON DUPLICATE KEY UPDATE 
                quantity = quantity + :qty_inc,
                updated_at = NOW()
        ");
        $stmtItem->execute([
            ':cid'     => $cartId,
            ':pid'     => $productId,
            ':qty'     => $quantity,
            ':price'   => $price,
            ':qty_inc' => $quantity
        ]);

        // Sync to legacy user_carts table for backward compatibility
        $this->syncLegacyUserCart($userId);

        // Sync to active session
        $this->syncSessionCartFromDb($userId);

        return $this->getCartSummary($userId);
    }

    /**
     * Update quantity of an item in the active cart
     */
    public function updateQuantity(int $userId, string $productId, int $quantity): array {
        if ($quantity <= 0) {
            return $this->removeItem($userId, $productId);
        }

        $cartId = $this->getOrCreateActiveCart($userId);

        $stmt = $this->db->prepare("
            UPDATE cart_items 
            SET quantity = :qty, updated_at = NOW()
            WHERE cart_id = :cid AND product_id = :pid
        ");
        $stmt->execute([
            ':qty' => $quantity,
            ':cid' => $cartId,
            ':pid' => $productId
        ]);

        $this->syncLegacyUserCart($userId);
        $this->syncSessionCartFromDb($userId);

        return $this->getCartSummary($userId);
    }

    /**
     * Remove an item completely from the active cart
     */
    public function removeItem(int $userId, string $productId): array {
        $cartId = $this->getOrCreateActiveCart($userId);

        $stmt = $this->db->prepare("
            DELETE FROM cart_items 
            WHERE cart_id = :cid AND product_id = :pid
        ");
        $stmt->execute([
            ':cid' => $cartId,
            ':pid' => $productId
        ]);

        $this->syncLegacyUserCart($userId);
        $this->syncSessionCartFromDb($userId);

        return $this->getCartSummary($userId);
    }

    /**
     * Clear all items from active cart
     */
    public function clearCart(int $userId): void {
        $cartId = $this->getOrCreateActiveCart($userId);

        $stmt = $this->db->prepare("DELETE FROM cart_items WHERE cart_id = :cid");
        $stmt->execute([':cid' => $cartId]);

        // Clear legacy
        $stmtLegacy = $this->db->prepare("DELETE FROM user_carts WHERE user_id = :uid");
        $stmtLegacy->execute([':uid' => $userId]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['cart'] = [];
        }
    }

    /**
     * Intelligently merge guest session cart into database cart
     * (combining quantities, deduplicating IDs)
     */
    public function mergeSessionCart(int $userId, array $guestItems): array {
        if (empty($guestItems)) {
            return $this->validateAndHydrate($userId);
        }

        $cartId = $this->getOrCreateActiveCart($userId);

        foreach ($guestItems as $productId => $guestQty) {
            $qty = (int)$guestQty;
            if ($qty <= 0) continue;

            // Fetch product base price
            $stmtProd = $this->db->prepare("SELECT base_price, is_active FROM products WHERE id = :pid LIMIT 1");
            $stmtProd->execute([':pid' => $productId]);
            $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);
            if (!$prod || empty($prod['is_active'])) {
                continue; // will be caught/ignored
            }
            $price = (float)$prod['base_price'];

            // Upsert into cart_items, adding guest quantity to any existing quantity
            $stmtUpsert = $this->db->prepare("
                INSERT INTO cart_items (cart_id, product_id, quantity, price_at_addition)
                VALUES (:cid, :pid, :qty, :price)
                ON DUPLICATE KEY UPDATE 
                    quantity = quantity + :qty_inc,
                    updated_at = NOW()
            ");
            $stmtUpsert->execute([
                ':cid'     => $cartId,
                ':pid'     => $productId,
                ':qty'     => $qty,
                ':price'   => $price,
                ':qty_inc' => $qty
            ]);
        }

        // Validate stock and pricing across all merged items
        return $this->validateAndHydrate($userId);
    }

    /**
     * Validate inventory stock levels & prices, update DB, and hydrate session
     */
    public function validateAndHydrate(int $userId): array {
        $cartId = $this->getOrCreateActiveCart($userId);
        $items = $this->getItems($userId);
        $notices = [];

        foreach ($items as $item) {
            $itemId = (int)$item['item_id'];
            $title = $item['title'];
            $currentStock = (int)$item['current_stock'];
            $currentPrice = (float)$item['current_price'];
            $priceAtAddition = (float)$item['price_at_addition'];
            $qty = (int)$item['quantity'];
            $isActive = !empty($item['is_active']);
            $isPhysical = ($item['product_type'] === 'physical');

            // 1. Check if product is discontinued/inactive
            if (!$isActive) {
                $del = $this->db->prepare("DELETE FROM cart_items WHERE id = :id");
                $del->execute([':id' => $itemId]);
                $notices[] = [
                    'type' => 'removed',
                    'message' => "\"{$title}\" is no longer available and was removed from your cart."
                ];
                continue;
            }

            // 2. Check stock level for physical products
            if ($isPhysical) {
                if ($currentStock <= 0) {
                    $del = $this->db->prepare("DELETE FROM cart_items WHERE id = :id");
                    $del->execute([':id' => $itemId]);
                    $notices[] = [
                        'type' => 'out_of_stock',
                        'message' => "\"{$title}\" is currently out of stock and was removed from your cart."
                    ];
                    continue;
                }

                if ($qty > $currentStock) {
                    $upd = $this->db->prepare("UPDATE cart_items SET quantity = :stock, updated_at = NOW() WHERE id = :id");
                    $upd->execute([':stock' => $currentStock, ':id' => $itemId]);
                    $notices[] = [
                        'type' => 'adjusted',
                        'message' => "\"{$title}\" quantity was adjusted from {$qty} to {$currentStock} due to available stock limits."
                    ];
                    $qty = $currentStock;
                }
            }

            // 3. Check price change
            if ($priceAtAddition > 0 && abs($priceAtAddition - $currentPrice) >= 0.01) {
                $updPrice = $this->db->prepare("UPDATE cart_items SET price_at_addition = :price, updated_at = NOW() WHERE id = :id");
                $updPrice->execute([':price' => $currentPrice, ':id' => $itemId]);
                $oldFmt = number_format($priceAtAddition, 2);
                $newFmt = number_format($currentPrice, 2);
                $notices[] = [
                    'type' => 'price_change',
                    'message' => "The price for \"{$title}\" has changed from ₱{$oldFmt} to ₱{$newFmt}."
                ];
            }
        }

        // Synchronize to session and legacy user_carts
        $this->syncLegacyUserCart($userId);
        $this->syncSessionCartFromDb($userId);

        $summary = $this->getCartSummary($userId);
        $summary['notices'] = $notices;
        return $summary;
    }

    /**
     * Mark active cart as converted upon completed checkout
     */
    public function markConverted(int $userId): void {
        $cartId = $this->getOrCreateActiveCart($userId);

        $stmt = $this->db->prepare("
            UPDATE carts 
            SET status = 'converted', updated_at = NOW() 
            WHERE id = :cid AND status = 'active'
        ");
        $stmt->execute([':cid' => $cartId]);

        // Clear legacy
        $stmtLegacy = $this->db->prepare("DELETE FROM user_carts WHERE user_id = :uid");
        $stmtLegacy->execute([':uid' => $userId]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['cart'] = [];
        }
    }

    /**
     * Compile structured summary array matching frontend requirements
     */
    public function getCartSummary(int $userId): array {
        $rawItems = $this->getItems($userId);
        $items = [];
        $total = 0.0;
        $count = 0;

        foreach ($rawItems as $item) {
            $qty = (int)$item['quantity'];
            $price = (float)$item['current_price'];
            $lineTotal = $price * $qty;

            $items[] = [
                'id'         => $item['product_id'],
                'title'      => $item['title'],
                'price'      => '₱' . number_format($price, 2),
                'quantity'   => $qty,
                'line_total' => number_format($lineTotal, 2),
                'image'      => $item['image'],
                'alt'        => $item['title']
            ];

            $total += $lineTotal;
            $count += $qty;
        }

        return [
            'itemCount'  => $count,
            'grandTotal' => number_format($total, 2),
            'items'      => $items,
            'notices'    => []
        ];
    }

    /**
     * Synchronize session cart array with active DB items
     */
    private function syncSessionCartFromDb(int $userId): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $cartId = $this->getOrCreateActiveCart($userId);
        $stmt = $this->db->prepare("SELECT product_id, quantity FROM cart_items WHERE cart_id = :cid");
        $stmt->execute([':cid' => $cartId]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $_SESSION['cart'] = $rows ?: [];
    }

    /**
     * Keep user_carts table in sync for backward compatibility
     */
    private function syncLegacyUserCart(int $userId): void {
        try {
            $cartId = $this->getOrCreateActiveCart($userId);

            // Fetch current items from cart_items
            $stmt = $this->db->prepare("SELECT product_id, quantity FROM cart_items WHERE cart_id = :cid");
            $stmt->execute([':cid' => $cartId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Overwrite in user_carts
            $del = $this->db->prepare("DELETE FROM user_carts WHERE user_id = :uid");
            $del->execute([':uid' => $userId]);

            if (!empty($items)) {
                $ins = $this->db->prepare("
                    INSERT INTO user_carts (user_id, product_id, quantity)
                    VALUES (:uid, :pid, :qty)
                ");
                foreach ($items as $item) {
                    $ins->execute([
                        ':uid' => $userId,
                        ':pid' => $item['product_id'],
                        ':qty' => (int)$item['quantity']
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log('[Cart] Legacy sync error: ' . $e->getMessage());
        }
    }
}
