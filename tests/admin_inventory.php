<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Service\AdminInventory;
use Doctrine\DBAL\DriverManager;

$db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
foreach ([
    'CREATE TABLE orders (id INTEGER PRIMARY KEY, order_number TEXT NOT NULL)',
    'CREATE TABLE products (id TEXT PRIMARY KEY, type TEXT NOT NULL)',
    'CREATE TABLE order_items (order_id INTEGER NOT NULL, product_id TEXT NOT NULL, product_name TEXT NOT NULL, quantity INTEGER NOT NULL)',
    'CREATE TABLE inventory (product_id TEXT PRIMARY KEY, current_stock INTEGER NOT NULL)',
    'CREATE TABLE inventory_transactions (product_id TEXT NOT NULL, change_amount INTEGER NOT NULL, transaction_type TEXT NOT NULL, reference_id TEXT, notes TEXT, created_by INTEGER)',
] as $sql) {
    $db->executeStatement($sql);
}
$db->insert('orders', ['id' => 1, 'order_number' => 'TEST-1']);
$db->insert('products', ['id' => 'panel', 'type' => 'physical']);
$db->insert('order_items', ['order_id' => 1, 'product_id' => 'panel', 'product_name' => 'Panel', 'quantity' => 2]);
$db->insert('inventory', ['product_id' => 'panel', 'current_stock' => 5]);

$inventory = new AdminInventory($db);
$stock = static fn (): int => (int) $db->fetchOne("SELECT current_stock FROM inventory WHERE product_id = 'panel'");
$assert = static function (int $expected, string $step) use ($stock): void {
    if ($stock() !== $expected) {
        throw new RuntimeException("$step: expected $expected units, got {$stock()}");
    }
};

foreach ([1, 2] as $cycle) {
    $db->transactional(static fn () => $inventory->deduct(1, null));
    $assert(3, "cycle $cycle deduction");
    $db->transactional(static fn () => $inventory->deduct(1, null));
    $assert(3, "cycle $cycle repeat deduction");
    $db->transactional(static fn () => $inventory->restore(1, null));
    $assert(5, "cycle $cycle restoration");
    $db->transactional(static fn () => $inventory->restore(1, null));
    $assert(5, "cycle $cycle repeat restoration");
}

if ((int) $db->fetchOne('SELECT COUNT(*) FROM inventory_transactions') !== 4) {
    throw new RuntimeException('Expected one deduction and one restoration per cycle.');
}

$db->executeStatement("UPDATE inventory SET current_stock = 1 WHERE product_id = 'panel'");
try {
    $db->transactional(static fn () => $inventory->deduct(1, null));
    throw new RuntimeException('Insufficient inventory was accepted.');
} catch (RuntimeException $error) {
    if (!str_starts_with($error->getMessage(), 'Insufficient inventory:')) {
        throw $error;
    }
}
$assert(1, 'rejected deduction');

echo "Admin inventory allocation cycles passed.\n";
