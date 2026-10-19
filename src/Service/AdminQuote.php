<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final class AdminQuote
{
    public function __construct(private readonly Connection $database)
    {
    }

    public function convert(int $id): array
    {
        return $this->database->transactional(function () use ($id): array {
            $quote = $this->database->fetchAssociative('SELECT * FROM quote_requests WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!$quote) throw new \RuntimeException("Quote request #$id was not found.");
            $existing = $this->database->fetchAssociative('SELECT id, order_number FROM orders WHERE notes LIKE :number LIMIT 1', ['number' => '%'.$quote['quote_number'].'%']);
            if ($existing) return ['id' => (int) $existing['id'], 'number' => $existing['order_number'], 'already' => true, 'quote' => $quote];
            $number = 'APD-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6));
            $total = (float) ($quote['quoted_amount'] ?? $quote['estimated_installation_cost'] ?? 15000);
            $subtotal = round($total / 1.08, 2);
            $userId = $quote['user_id'] ?: $this->database->fetchOne('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1', ['email' => $quote['email']]);
            $this->database->insert('orders', [
                'order_number' => $number, 'user_id' => $userId ?: null,
                'customer_name' => $quote['company_name'].' ('.$quote['contact_person'].')',
                'customer_email' => $quote['email'], 'customer_phone' => $quote['phone'],
                'property_type' => 'Commercial', 'payment_method' => 'Direct Commercial Wire / Invoiced',
                'status' => 'processing', 'subtotal' => $subtotal, 'tax_amount' => $total - $subtotal,
                'total_amount' => $total,
                'shipping_address' => $quote['installation_address'] ?: 'Site address as per commercial proposal',
                'notes' => "Converted from Commercial RFQ #{$quote['quote_number']}.\nFacility: {$quote['facility_type']} ({$quote['facility_size']} sqm).\nEst. System Size: {$quote['estimated_system_size']} kW.",
                'installation_head' => $quote['installation_head'] ?: null,
                'installation_date' => $quote['installation_date'] ?: null,
            ]);
            $orderId = (int) $this->database->lastInsertId();
            $this->database->insert('order_items', [
                'order_id' => $orderId, 'product_id' => 'commercial-grids',
                'product_name' => 'Commercial Grid Turnkey Solar Project - '.($quote['estimated_system_size'] ? $quote['estimated_system_size'].' kW' : 'Custom Array'),
                'quantity' => 1, 'unit_price' => $subtotal, 'total_price' => $subtotal,
            ]);
            $this->database->insert('order_status_history', [
                'order_id' => $orderId, 'status' => 'processing',
                'notes' => "Official order created by admin conversion from Quote #{$quote['quote_number']}",
            ]);
            $this->database->executeStatement("UPDATE quote_requests SET status = 'accepted', updated_at = CURRENT_TIMESTAMP WHERE id = :id", ['id' => $id]);
            return ['id' => $orderId, 'number' => $number, 'already' => false, 'quote' => $quote];
        });
    }

    public function respond(int $id, string $status, string $head, string $date, string $notes, ?int $adminId): array
    {
        if (!in_array($status, ['confirmed', 'new', 'in_progress', 'completed', 'cancelled'], true)) $status = 'confirmed';
        return $this->database->transactional(function () use ($id, $status, $head, $date, $notes, $adminId): array {
            $quote = $this->database->fetchAssociative('SELECT * FROM quote_requests WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!$quote) throw new \RuntimeException("Quote request #$id was not found.");
            $this->database->executeStatement('UPDATE quote_requests SET status = :status, installation_head = :head, installation_date = :date, admin_notes = :notes, quoted_by = :admin, quoted_at = NOW() WHERE id = :id', [
                'status' => $status, 'head' => $head ?: null, 'date' => $date ?: null,
                'notes' => $notes, 'admin' => $adminId, 'id' => $id,
            ]);
            $reference = 'INSTALL-'.$quote['quote_number'];
            $existingBooking = $this->database->fetchAssociative('SELECT id FROM service_bookings WHERE booking_reference = :ref', ['ref' => $reference]);
            $bookingStatus = match ($status) {
                'confirmed', 'in_progress' => 'confirmed',
                'completed' => 'completed', 'cancelled' => 'cancelled', default => 'pending',
            };
            $bookingDate = $date ?: date('Y-m-d');
            $bookingNotes = "Commercial Grid Solar Installation\nCompany: {$quote['company_name']}\nFacility: {$quote['facility_size']} sqm\n".$notes;
            if ($existingBooking) {
                $this->database->executeStatement('UPDATE service_bookings SET assigned_technician = :head, preferred_date = :date, status = :status, access_notes = :notes WHERE id = :id', [
                    'head' => $head ?: 'Unassigned', 'date' => $bookingDate,
                    'status' => $bookingStatus, 'notes' => $bookingNotes, 'id' => $existingBooking['id'],
                ]);
            } elseif ($status === 'confirmed' || $head !== '') {
                $this->database->insert('service_bookings', [
                    'booking_reference' => $reference,
                    'customer_name' => $quote['company_name'].' ('.$quote['contact_person'].')',
                    'customer_email' => $quote['email'], 'customer_phone' => $quote['phone'],
                    'service_type' => 'installation', 'preferred_date' => $bookingDate,
                    'preferred_time_slot' => 'morning', 'status' => $bookingStatus,
                    'address' => $quote['installation_address'] ?: 'Project site address',
                    'access_notes' => $bookingNotes, 'assigned_technician' => $head ?: 'Unassigned',
                ]);
            }
            $userId = $quote['user_id'] ?: $this->database->fetchOne('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1', ['email' => $quote['email']]);
            $orderReference = 'APD-INST-'.$quote['quote_number'];
            $existingOrder = $this->database->fetchAssociative('SELECT id, notes FROM orders WHERE order_number = :ref OR notes LIKE :text LIMIT 1', [
                'ref' => $orderReference, 'text' => '%'.$quote['quote_number'].'%',
            ]);
            if (in_array($status, ['confirmed', 'in_progress', 'completed'], true)) {
                $total = (float) ($quote['quoted_amount'] ?? $quote['estimated_installation_cost'] ?? 0);
                $subtotal = round($total / 1.12, 2);
                $orderStatus = match ($status) {
                    'in_progress' => 'processing', 'completed' => 'delivered', default => 'confirmed',
                };
                $description = match ($status) {
                    'in_progress' => 'marked installation in progress',
                    'completed' => 'marked installation completed',
                    default => 'confirmed by admin & head assigned',
                };
                $orderNotes = ucfirst(str_replace('_', ' ', $status))." Commercial Grid Solar Installation\nRFQ: {$quote['quote_number']}\nFacility: {$quote['facility_size']} sqm\nAssigned Head: ".($head ?: 'Unassigned')."\nScheduled Date: ".($date ?: 'TBD')."\n".$notes;
                if ($existingOrder) {
                    $original = (string) ($existingOrder['notes'] ?? '');
                    if (str_contains($original, 'Estimated System Capacity')) $orderNotes .= "\n\n".$original;
                    $this->database->executeStatement('UPDATE orders SET status = :status, user_id = COALESCE(:user, user_id), installation_head = :head, installation_date = :date, notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id', [
                        'status' => $orderStatus, 'user' => $userId ?: null, 'head' => $head ?: null,
                        'date' => $date ?: null, 'notes' => $orderNotes, 'id' => $existingOrder['id'],
                    ]);
                    $orderId = (int) $existingOrder['id'];
                } else {
                    $this->database->insert('orders', [
                        'order_number' => $orderReference, 'user_id' => $userId ?: null,
                        'customer_name' => $quote['company_name'].' ('.$quote['contact_person'].')',
                        'customer_email' => $quote['email'], 'customer_phone' => $quote['phone'],
                        'property_type' => 'Commercial', 'payment_method' => 'Commercial Installation Contract',
                        'status' => $orderStatus, 'subtotal' => $subtotal, 'tax_amount' => $total - $subtotal,
                        'total_amount' => $total, 'shipping_address' => $quote['installation_address'] ?: 'Project site address',
                        'notes' => $orderNotes, 'installation_head' => $head ?: null, 'installation_date' => $date ?: null,
                    ]);
                    $orderId = (int) $this->database->lastInsertId();
                    $this->database->insert('order_items', [
                        'order_id' => $orderId, 'product_id' => 'commercial-grids',
                        'product_name' => 'Commercial Grid Turnkey Solar Installation - '.$quote['company_name'],
                        'quantity' => 1, 'unit_price' => $subtotal, 'total_price' => $subtotal,
                    ]);
                }
                $this->database->insert('order_status_history', [
                    'order_id' => $orderId, 'status' => $orderStatus,
                    'notes' => 'Commercial grid '.$description.': '.($head ?: 'Unassigned'),
                ]);
            } elseif ($existingOrder && $status === 'cancelled') {
                $this->database->executeStatement("UPDATE orders SET status = 'cancelled' WHERE id = :id", ['id' => $existingOrder['id']]);
            }
            $suffix = $head !== '' ? ' and assigned to '.$head : '';
            $message = match ($status) {
                'in_progress' => 'Installation status successfully updated to In Progress'.$suffix.'.',
                'completed' => 'Installation status successfully updated to Completed'.$suffix.'.',
                'confirmed' => 'Commercial installation request successfully confirmed'.$suffix.'.',
                default => 'Commercial installation request updated to '.ucfirst(str_replace('_', ' ', $status)).$suffix.'.',
            };
            return ['message' => $message, 'quote' => $quote];
        });
    }
}
