<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final class AdminOrder
{
    private const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

    public function __construct(private readonly Connection $database, private readonly AdminInventory $inventory)
    {
    }

    public function updateStatus(int $orderId, string $newStatus, ?int $adminId, string $adminName = '', string $adminNote = ''): array
    {
        if ($orderId <= 0 || !in_array($newStatus, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid order or status.');
        }
        return $this->database->transactional(function () use ($orderId, $newStatus, $adminId, $adminName, $adminNote): array {
            $order = $this->database->fetchAssociative('SELECT * FROM orders WHERE id = :id FOR UPDATE', ['id' => $orderId]);
            if (!$order) {
                throw new \RuntimeException("Order #$orderId was not found.");
            }
            if ($order['status'] === $newStatus) {
                return ['order' => $order, 'changed' => false];
            }
            if ($order['status'] === 'pending' && in_array($newStatus, ['processing', 'shipped', 'delivered'], true)) {
                $this->inventory->deduct($orderId, $adminId);
            }
            if (in_array($order['status'], ['processing', 'shipped', 'delivered'], true) && in_array($newStatus, ['cancelled', 'refunded'], true)) {
                $this->inventory->restore($orderId, $adminId);
            }
            if ($adminId !== null) {
                $this->database->executeStatement('SET @current_admin_id = :id', ['id' => $adminId]);
            }
            $notes = (string) ($order['notes'] ?? '');
            if ($adminNote !== '') {
                $notes .= "\n[".date('Y-m-d H:i').' '.($adminName ?: 'Admin').']: '.$adminNote;
            }
            $this->database->executeStatement('UPDATE orders SET status = :status, notes = :notes WHERE id = :id', ['status' => $newStatus, 'notes' => $notes, 'id' => $orderId]);
            return ['order' => $order, 'changed' => true];
        });
    }
}
