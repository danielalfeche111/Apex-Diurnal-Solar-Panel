<?php

declare(strict_types=1);

namespace App\Service\Customer;

use Doctrine\DBAL\Connection;

final class CustomerCart
{
    public function __construct(
        private readonly Connection $db,
        private readonly CustomerSession $identity,
        private readonly ProductCatalog $catalog,
    ) {
    }

    public function sessionCart(): array
    {
        $cart = $this->identity->session()->get('cart', []);
        return is_array($cart) ? $cart : [];
    }

    public function summary(?int $userId = null, bool $hydrate = false): array
    {
        if ($userId === null) {
            $items = [];
            $total = 0.0;
            $count = 0;
            foreach ($this->sessionCart() as $id => $quantity) {
                $product = $this->catalog->byId((string) $id);
                $quantity = (int) $quantity;
                if ($product === null || $quantity <= 0) {
                    continue;
                }
                $price = (float) $product['price'];
                $items[] = [
                    'id' => $id,
                    'title' => $product['title'],
                    'price' => '₱'.number_format($price, 2),
                    'quantity' => $quantity,
                    'line_total' => number_format($price * $quantity, 2),
                    'image' => $product['image'],
                    'alt' => $product['alt'],
                ];
                $total += $price * $quantity;
                $count += $quantity;
            }
            return ['itemCount' => $count, 'grandTotal' => number_format($total, 2), 'items' => $items, 'notices' => []];
        }

        $notices = $hydrate ? $this->validateAndHydrate($userId) : [];
        $items = [];
        $total = 0.0;
        $count = 0;
        foreach ($this->rows($userId) as $row) {
            $quantity = (int) $row['quantity'];
            $price = (float) $row['current_price'];
            $items[] = [
                'id' => $row['product_id'],
                'title' => $row['title'],
                'price' => '₱'.number_format($price, 2),
                'quantity' => $quantity,
                'line_total' => number_format($price * $quantity, 2),
                'image' => $row['image'],
                'alt' => $row['title'],
            ];
            $total += $price * $quantity;
            $count += $quantity;
        }
        return ['itemCount' => $count, 'grandTotal' => number_format($total, 2), 'items' => $items, 'notices' => $notices];
    }

    public function apply(string $action, string $productId, int $quantity, ?int $userId): array
    {
        if ($userId === null) {
            $cart = $this->sessionCart();
            if ($action === 'clear') {
                $cart = [];
            } elseif ($productId !== '' && $this->catalog->byId($productId) !== null) {
                if ($action === 'remove' || ($action === 'update' && $quantity <= 0)) {
                    unset($cart[$productId]);
                } elseif ($action === 'update') {
                    $cart[$productId] = max(1, $quantity);
                } elseif ($action === 'add') {
                    // Preserve the storefront's registration gate: guest add is a no-op.
                    // The update action can still restore an existing session cart.
                }
            }
            $this->identity->session()->set('cart', $cart);
            return $this->summary();
        }

        $cartId = $this->activeCartId($userId);
        if ($action === 'clear') {
            $this->db->delete('cart_items', ['cart_id' => $cartId]);
        } elseif ($productId !== '') {
            if ($action === 'remove' || ($action === 'update' && $quantity <= 0)) {
                $this->db->delete('cart_items', ['cart_id' => $cartId, 'product_id' => $productId]);
            } elseif ($action === 'update') {
                $this->db->update('cart_items', ['quantity' => max(1, $quantity), 'updated_at' => date('Y-m-d H:i:s')], ['cart_id' => $cartId, 'product_id' => $productId]);
            } elseif ($action === 'add') {
                $product = $this->db->fetchAssociative('SELECT base_price, is_active FROM products WHERE id = :id', ['id' => $productId]);
                if (!$product || !(bool) $product['is_active']) {
                    throw new \InvalidArgumentException('Product not found or currently unavailable.');
                }
                $this->db->executeStatement(
                    'INSERT INTO cart_items (cart_id, product_id, quantity, price_at_addition) VALUES (:cart, :product, :quantity, :price) ON DUPLICATE KEY UPDATE quantity = quantity + :increment, updated_at = NOW()',
                    ['cart' => $cartId, 'product' => $productId, 'quantity' => max(1, $quantity), 'price' => $product['base_price'], 'increment' => max(1, $quantity)],
                );
            }
        }
        $this->sync($userId);
        return $this->summary($userId);
    }

    public function merge(int $userId, array $guestItems): array
    {
        foreach ($guestItems as $id => $quantity) {
            if (is_string($id) && (int) $quantity > 0) {
                try {
                    $this->apply('add', $id, (int) $quantity, $userId);
                } catch (\InvalidArgumentException) {
                    // Discontinued products are left out of the merged cart.
                }
            }
        }
        return $this->summary($userId, true);
    }

    public function markConverted(int $userId): void
    {
        $cartId = $this->activeCartId($userId);
        $this->db->update('carts', ['status' => 'converted', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $cartId, 'status' => 'active']);
        $this->db->delete('user_carts', ['user_id' => $userId]);
        $this->identity->session()->set('cart', []);
    }

    private function activeCartId(int $userId): int
    {
        $id = $this->db->fetchOne("SELECT id FROM carts WHERE user_id = :user AND status = 'active' ORDER BY id DESC LIMIT 1", ['user' => $userId]);
        if ($id) {
            return (int) $id;
        }
        $this->db->insert('carts', ['user_id' => $userId, 'status' => 'active']);
        return (int) $this->db->lastInsertId();
    }

    private function rows(int $userId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT ci.id AS item_id, ci.product_id, ci.quantity, ci.price_at_addition,
                    p.name AS title, p.base_price AS current_price, p.image, p.type AS product_type, p.is_active,
                    COALESCE(inv.current_stock, 999) AS current_stock
             FROM cart_items ci JOIN products p ON p.id = ci.product_id
             LEFT JOIN inventory inv ON inv.product_id = ci.product_id
             WHERE ci.cart_id = :cart ORDER BY ci.id ASC",
            ['cart' => $this->activeCartId($userId)],
        );
    }

    private function validateAndHydrate(int $userId): array
    {
        $notices = [];
        foreach ($this->rows($userId) as $row) {
            $title = $row['title'];
            $quantity = (int) $row['quantity'];
            $stock = (int) $row['current_stock'];
            if (!(bool) $row['is_active']) {
                $this->db->delete('cart_items', ['id' => $row['item_id']]);
                $notices[] = ['type' => 'removed', 'message' => "\"{$title}\" is no longer available and was removed from your cart."];
                continue;
            }
            if ($row['product_type'] === 'physical' && $stock <= 0) {
                $this->db->delete('cart_items', ['id' => $row['item_id']]);
                $notices[] = ['type' => 'out_of_stock', 'message' => "\"{$title}\" is currently out of stock and was removed from your cart."];
                continue;
            }
            if ($row['product_type'] === 'physical' && $quantity > $stock) {
                $this->db->update('cart_items', ['quantity' => $stock, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $row['item_id']]);
                $notices[] = ['type' => 'adjusted', 'message' => "\"{$title}\" quantity was adjusted from {$quantity} to {$stock} due to available stock limits."];
            }
            $price = (float) $row['current_price'];
            $prior = (float) $row['price_at_addition'];
            if ($prior > 0 && abs($prior - $price) >= .01) {
                $this->db->update('cart_items', ['price_at_addition' => $price, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $row['item_id']]);
                $notices[] = ['type' => 'price_change', 'message' => 'The price for "'.$title.'" has changed from ₱'.number_format($prior, 2).' to ₱'.number_format($price, 2).'.'];
            }
        }
        $this->sync($userId);
        return $notices;
    }

    private function sync(int $userId): void
    {
        $rows = $this->db->fetchAllAssociative('SELECT product_id, quantity FROM cart_items WHERE cart_id = :cart', ['cart' => $this->activeCartId($userId)]);
        $cart = [];
        $this->db->delete('user_carts', ['user_id' => $userId]);
        foreach ($rows as $row) {
            $cart[$row['product_id']] = (int) $row['quantity'];
            $this->db->insert('user_carts', ['user_id' => $userId, 'product_id' => $row['product_id'], 'quantity' => (int) $row['quantity']]);
        }
        $this->identity->session()->set('cart', $cart);
    }
}
