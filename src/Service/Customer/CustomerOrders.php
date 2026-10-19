<?php

declare(strict_types=1);

namespace App\Service\Customer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/** Customer order queries and mutations, shared by pages and JSON endpoints. */
final class CustomerOrders
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function claimUnassigned(int $userId, string $email): void
    {
        if ($email === '') {
            return;
        }
        $this->db->executeStatement(
            "UPDATE orders SET user_id = :uid WHERE user_id IS NULL AND (LOWER(customer_email) = LOWER(:email) OR LOWER(customer_email) = REPLACE(LOWER(:alternate1), '@gmail.com', '@gmai.com') OR LOWER(customer_email) = REPLACE(LOWER(:alternate2), '@gmai.com', '@gmail.com'))",
            ['uid' => $userId, 'email' => $email, 'alternate1' => $email, 'alternate2' => $email],
        );
        $this->db->executeStatement(
            "UPDATE orders o JOIN quote_requests q ON (o.order_number = CONCAT('APD-INST-', q.quote_number) OR o.notes LIKE CONCAT('%', q.quote_number, '%')) SET o.user_id = :uid WHERE o.user_id IS NULL AND q.user_id = :quote_user",
            ['uid' => $userId, 'quote_user' => $userId],
        );
    }

    public function list(int $userId, array $filters, int $limit = 10): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, min(50, $limit));
        $where = ['o.user_id = :user_id'];
        $params = ['user_id' => $userId];
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (in_array($status, ['pending', 'client_confirmed', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'], true)) {
            $where[] = 'o.status = :status';
            $params['status'] = $status;
        }
        foreach (['start_date' => '>=', 'end_date' => '<='] as $field => $comparison) {
            $date = trim((string) ($filters[$field] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $where[] = 'o.created_at '.$comparison.' :'.$field;
                $params[$field] = $date.($field === 'start_date' ? ' 00:00:00' : ' 23:59:59');
            }
        }
        $whereSql = implode(' AND ', $where);
        $sortMap = ['order_number' => 'o.order_number', 'created_at' => 'o.created_at', 'total_amount' => 'o.total_amount', 'status' => 'o.status'];
        $sortBy = strtolower(trim((string) ($filters['sort_by'] ?? 'created_at')));
        $sortCol = $sortMap[$sortBy] ?? 'o.created_at';
        $sortDir = strtolower(trim((string) ($filters['sort_dir'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';
        $total = (int) $this->db->fetchOne('SELECT COUNT(*) FROM orders o WHERE '.$whereSql, $params);
        $all = (int) $this->db->fetchOne('SELECT COUNT(*) FROM orders WHERE user_id = :id', ['id' => $userId]);
        $rows = $this->db->fetchAllAssociative(
            "SELECT o.id, o.order_number, o.created_at, o.status, o.property_type, o.total_amount, COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = o.id), 0) AS item_count FROM orders o WHERE {$whereSql} ORDER BY {$sortCol} {$sortDir}, o.id DESC LIMIT :limit OFFSET :offset",
            $params + ['limit' => $limit, 'offset' => ($page - 1) * $limit],
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        );
        return ['orders' => $rows, 'total' => $total, 'all' => $all, 'page' => $page, 'limit' => $limit, 'pages' => max(1, (int) ceil($total / $limit))];
    }

    public function detail(int $userId, int $orderId): ?array
    {
        $order = $this->db->fetchAssociative('SELECT * FROM orders WHERE id = :id AND user_id = :uid LIMIT 1', ['id' => $orderId, 'uid' => $userId]);
        if (!$order) {
            return null;
        }
        return $this->complete($order);
    }

    public function receipt(int $userId, ?int $orderId, string $orderNumber): ?array
    {
        $order = $orderId ? $this->db->fetchAssociative('SELECT * FROM orders WHERE id = :id AND user_id = :uid LIMIT 1', ['id' => $orderId, 'uid' => $userId]) : null;
        if ($orderId === null && $orderNumber !== '') {
            $order = $this->db->fetchAssociative('SELECT * FROM orders WHERE order_number = :number AND user_id = :uid LIMIT 1', ['number' => $orderNumber, 'uid' => $userId]);
        }
        return $order ? $this->complete($order) : null;
    }

    private function complete(array $order): array
    {
        $orderId = (int) $order['id'];
        $items = $this->db->fetchAllAssociative('SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = :oid ORDER BY oi.id ASC', ['oid' => $orderId]);
        $history = $this->db->fetchAllAssociative('SELECT * FROM order_status_history WHERE order_id = :oid ORDER BY changed_at ASC, id ASC', ['oid' => $orderId]);
        if ($history === []) {
            $history[] = ['id' => 0, 'status' => $order['status'], 'changed_at' => $order['created_at'], 'notes' => 'Order placed'];
        }
        $quote = [];
        if ($order['property_type'] === 'Commercial' && preg_match('/RFQ-\d{8}-\d{4}/', $order['order_number'].' '.($order['notes'] ?? ''), $match)) {
            $quote = $this->db->fetchAssociative('SELECT * FROM quote_requests WHERE quote_number = :number LIMIT 1', ['number' => $match[0]]) ?: [];
            if ($quote !== [] && ($quote['status'] === 'confirmed' || !empty($quote['installation_head'])) && in_array($order['status'], ['pending', 'client_confirmed'], true)) {
                $this->db->executeStatement("UPDATE orders SET status = 'confirmed', installation_head = COALESCE(:head, installation_head), installation_date = COALESCE(:date, installation_date), updated_at = CURRENT_TIMESTAMP WHERE id = :id", ['head' => $quote['installation_head'] ?: null, 'date' => $quote['installation_date'] ?: null, 'id' => $orderId]);
                $order['status'] = 'confirmed';
                $order['installation_head'] = $quote['installation_head'] ?: $order['installation_head'];
                $order['installation_date'] = $quote['installation_date'] ?: $order['installation_date'];
            }
        }
        return ['order' => $order, 'items' => $items, 'history' => $history, 'quote' => $quote];
    }

    public function customerAction(int $userId, int $orderId, string $action): string
    {
        $order = $this->db->fetchAssociative('SELECT * FROM orders WHERE id = :id AND user_id = :uid LIMIT 1', ['id' => $orderId, 'uid' => $userId]);
        if (!$order) {
            throw new \DomainException('Order not found or access denied.');
        }
        if ($action === 'confirm' && $order['status'] !== 'pending') {
            throw new \DomainException('This order cannot be confirmed at this stage.');
        }
        if ($action === 'cancel' && !in_array($order['status'], ['pending', 'client_confirmed'], true)) {
            throw new \DomainException('This order can no longer be cancelled.');
        }
        $quoteNumber = preg_match('/RFQ-\d{8}-\d{4}/', $order['order_number'].' '.($order['notes'] ?? ''), $match) ? $match[0] : null;
        $status = $action === 'confirm' ? 'client_confirmed' : 'cancelled';
        $this->db->beginTransaction();
        try {
            $this->db->update('orders', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $orderId]);
            if ($quoteNumber !== null) {
                $this->db->update('quote_requests', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], ['quote_number' => $quoteNumber]);
                if ($action === 'cancel') {
                    $this->db->update('service_bookings', ['status' => 'cancelled'], ['booking_reference' => 'INST-'.$quoteNumber]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
        return $action === 'confirm'
            ? 'Commercial grid installation successfully confirmed! Awaiting admin approval and head engineer assignment.'
            : 'Commercial installation request has been cancelled.';
    }
}
