<?php

declare(strict_types=1);

namespace App\Service\Customer;

use Doctrine\DBAL\Connection;

final class CheckoutOrder
{
    public function __construct(private readonly Connection $db, private readonly CustomerCart $cart)
    {
    }

    public function prices(): array
    {
        return array_map(static fn (array $product): float => $product['price'], $this->products());
    }

    private function products(): array
    {
        $products = [];
        foreach ($this->db->fetchAllAssociative("SELECT id, name, base_price FROM products WHERE is_active = 1 AND type <> 'custom' ORDER BY CASE id WHEN 'residential-arrays' THEN 0 WHEN 'advanced-solar-inverter' THEN 1 WHEN 'installation-booking' THEN 2 ELSE 3 END, created_at, id") as $row) {
            $products[$row['name']] = ['id' => $row['id'], 'price' => (float) $row['base_price']];
        }
        return $products;
    }

    public function create(int $userId, array $details, array $selected, float $subtotal, float $tax, float $total): string
    {
        $orderNumber = 'APD-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        $paymentMethod = $details['payment_method'];
        if ($paymentMethod === 'Credit Card' && $details['masked_card'] !== '') {
            $paymentMethod .= ' ('.$details['masked_card'].')';
        }
        $address = trim(implode(', ', array_filter([$details['street_address'], $details['city'], $details['province']])).($details['postal_code'] !== '' ? ' '.$details['postal_code'] : ''));
        $this->db->beginTransaction();
        try {
            $products = $this->products();
            $currentSubtotal = 0.0;
            $includesInstallation = false;
            foreach ($selected as $name => $quantity) {
                if (!isset($products[$name])) {
                    throw new \InvalidArgumentException('A selected product is no longer available.');
                }
                $currentSubtotal += $products[$name]['price'] * $quantity;
                $includesInstallation = $includesInstallation || $products[$name]['id'] === 'installation-booking';
            }
            if (abs($currentSubtotal - $subtotal) > 0.001) {
                throw new \RuntimeException('A product price changed during checkout. Please review the order.');
            }
            $this->db->insert('orders', [
                'order_number' => $orderNumber, 'user_id' => $userId,
                'customer_name' => $details['full_name'], 'customer_email' => $details['email'], 'customer_phone' => $details['phone'],
                'property_type' => $details['property_type'], 'payment_method' => $paymentMethod, 'status' => 'pending',
                'subtotal' => $subtotal, 'tax_amount' => $tax, 'total_amount' => $total,
                'shipping_address' => $address, 'notes' => $details['notes'],
            ]);
            $orderId = (int) $this->db->lastInsertId();
            foreach ($selected as $name => $quantity) {
                $product = $products[$name];
                $this->db->insert('order_items', [
                    'order_id' => $orderId, 'product_id' => $product['id'], 'product_name' => $name,
                    'quantity' => $quantity, 'unit_price' => $product['price'], 'total_price' => $product['price'] * $quantity,
                ]);
            }
            if ($includesInstallation) {
                $this->db->insert('service_bookings', [
                    'booking_reference' => 'INST-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(2))),
                    'order_id' => $orderId, 'customer_name' => $details['full_name'], 'customer_email' => $details['email'],
                    'customer_phone' => $details['phone'], 'service_type' => 'installation',
                    'preferred_date' => date('Y-m-d', strtotime('+7 days')), 'preferred_time_slot' => 'morning',
                    'status' => 'pending', 'address' => $address, 'access_notes' => $details['notes'],
                ]);
            }
            $this->cart->markConverted($userId);
            $this->db->commit();
            return $orderNumber;
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }
}
