<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Database\BackupManager;
use App\Database\DatabaseConnection;
use App\Database\SchemaManager;
use App\Database\SqlScript;

$url = getenv('TEST_DATABASE_URL');
$restoreUrl = getenv('TEST_RESTORE_DATABASE_URL');
if (!$url || !$restoreUrl) {
    throw new RuntimeException('Set TEST_DATABASE_URL and TEST_RESTORE_DATABASE_URL to two EMPTY databases with "test" in their names.');
}
$database = new DatabaseConnection($url);
$restoreDatabase = new DatabaseConnection($restoreUrl);
foreach ([$database, $restoreDatabase] as $connection) {
    if (!str_contains(strtolower($connection->getDatabaseName()), 'test') || strtolower($connection->getDatabaseName()) === 'solar_db') {
        throw new RuntimeException('Refusing to use a database without "test" in its name.');
    }
    if ($connection->ensureDatabase()->query('SHOW TABLES')->fetchAll() !== []) {
        throw new RuntimeException('Tests require EMPTY databases; nothing was dropped. Choose new database names.');
    }
}
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
};
$projectDir = dirname(__DIR__, 2);
$manager = new SchemaManager($database, $projectDir);
$manager->install();
$pdo = $database->getConnection();
$assert(count($manager->check()['tables']) === 14 && $manager->check()['problems'] === [], 'Fresh schema contains all 14 tables and both triggers');
$hash = $pdo->query("SELECT password_hash FROM admin_users WHERE username = 'admin'")->fetchColumn();
$assert(password_verify('password', $hash), 'Original seed password hash preserved');
$pdo->exec("UPDATE inventory SET current_stock = 17 WHERE product_id = 'residential-arrays'");
$pdo->exec("INSERT INTO users(email, password_hash, full_name) VALUES ('migration-preservation@example.test', 'original-hash', 'Solar; \'quote\' 用户')");
$userId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO orders(order_number, user_id, customer_name, customer_email, customer_phone, payment_method, shipping_address, total_amount) VALUES ('DB-REGRESSION-001', ".$userId.", 'Preserved', 'migration-preservation@example.test', '09123456789', 'Cash', 'Original address', 8120.50)");
$orderId = (int) $pdo->lastInsertId();
$pdo->exec("UPDATE orders SET status = 'confirmed' WHERE id = ".$orderId);
$assert((int) $pdo->query('SELECT COUNT(*) FROM order_status_history WHERE order_id = '.$orderId)->fetchColumn() === 2, 'Insert and update history triggers preserve order lifecycle');
$before = $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();
$manager->install();
$assert($before === $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll(), 'Repeat installation preserves users and password hashes exactly');
$assert((int) $pdo->query("SELECT current_stock FROM inventory WHERE product_id = 'residential-arrays'")->fetchColumn() === 17, 'Repeat installation does not reset stock');

// Simulate earlier supported schema versions without removing customer records.
$pdo->exec('ALTER TABLE users DROP COLUMN phone, DROP COLUMN postal_code');
$pdo->exec("ALTER TABLE commercial_leads MODIFY preferred_time_slot ENUM('Morning','Afternoon') NULL, MODIFY facility_type ENUM('Manufacturing Plant','Commercial Building','Warehouse','Agricultural','School') NOT NULL");
$pdo->exec("ALTER TABLE service_bookings MODIFY preferred_time_slot ENUM('morning','afternoon') NOT NULL");
$assert($manager->check()['problems'] !== [], 'Read-only schema check detects incompatible legacy fields and enum values');
$manager->install();
$assert($manager->check()['problems'] === [], 'Partial legacy profiles and appointment columns upgrade successfully');
$facilityColumn = $pdo->query("SHOW COLUMNS FROM commercial_leads LIKE 'facility_type'")->fetch();
$assert(str_contains($facilityColumn['Type'], 'House / Residential') && $facilityColumn['Null'] === 'YES', 'Enum expansion and optional RFQ fields survive the same upgrade');
$assert(str_contains($facilityColumn['Type'], "'Commercial'"), 'Commercial RFQ facility type is accepted without changing its form value');
$pdo->exec("INSERT INTO service_bookings(booking_reference, customer_name, customer_email, customer_phone, service_type, preferred_date, preferred_time_slot) VALUES('DB-SLOT-001', 'Slot Test', 'slot@example.test', '09123456789', 'installation', '2030-01-01', '08:00 AM - 10:00 AM')");
$assert($pdo->query("SELECT preferred_time_slot FROM service_bookings WHERE booking_reference = 'DB-SLOT-001'")->fetchColumn() === '08:00 AM - 10:00 AM', 'Existing two-hour UI appointment slots round-trip unchanged');
$script = "-- comment\nINSERT INTO t VALUES ('semicolon; and \\'quote', '用户');\nDELIMITER //\nCREATE TRIGGER example AFTER INSERT ON t FOR EACH ROW BEGIN SET @a = 'a;b'; END//\nDELIMITER ;\n";
$assert(count(SqlScript::statements($script)) === 2, 'SQL parser handles quotes, comments, Unicode and trigger delimiters');

$backupFile = $projectDir.'/var/backups/schema-regression-'.bin2hex(random_bytes(4)).'.sql';
if (!is_dir(dirname($backupFile))) {
    mkdir(dirname($backupFile), 0700, true);
}
$backup = new BackupManager($database);
$expectedCounts = $backup->backup($backupFile);
$actualCounts = (new BackupManager($restoreDatabase))->restore($backupFile);
$assert($expectedCounts === $actualCounts, 'Backup restores every table and row count');
$restored = $restoreDatabase->getConnection();
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $sql = 'SELECT * FROM '.DatabaseConnection::identifier($table).' ORDER BY id';
    $assert($pdo->query($sql)->fetchAll() === $restored->query($sql)->fetchAll(), 'Exact record preservation: '.$table);
}
$refused = false;
try {
    (new BackupManager($restoreDatabase))->restore($backupFile);
} catch (RuntimeException $exception) {
    $refused = str_contains($exception->getMessage(), 'empty database');
}
$assert($refused, 'Restore refuses to overwrite a populated database');
echo "All database regression checks passed. Databases and private backup retained for inspection.\n";
