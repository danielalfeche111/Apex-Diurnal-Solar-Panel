<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Database\DatabaseConnection;

$sourceUrl = getenv('SOURCE_DATABASE_URL');
$targetUrl = getenv('TARGET_DATABASE_URL');
if (!$sourceUrl || !$targetUrl) {
    fwrite(STDERR, "Set SOURCE_DATABASE_URL and TARGET_DATABASE_URL. This comparison only reads both databases.\n");
    exit(1);
}
$source = (new DatabaseConnection($sourceUrl))->getConnection();
$target = (new DatabaseConnection($targetUrl))->getConnection();
$sourceTables = $source->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
$targetTables = $target->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
if ($sourceTables !== $targetTables) {
    throw new RuntimeException('Table/view sets differ.');
}
$total = 0;
foreach ($sourceTables as [$table, $type]) {
    if ($type === 'VIEW') {
        continue;
    }
    $identifier = DatabaseConnection::identifier($table);
    $fingerprint = static function (PDO $pdo) use ($identifier): array {
        $hashes = [];
        foreach ($pdo->query('SELECT * FROM '.$identifier) as $row) {
            $hashes[] = hash('sha256', serialize($row));
        }
        sort($hashes, SORT_STRING);

        return [count($hashes), hash('sha256', implode('', $hashes))];
    };
    $expected = $fingerprint($source);
    if ($fingerprint($target) !== $expected) {
        throw new RuntimeException('Record fingerprint differs for '.$table);
    }
    $definition = static fn (PDO $pdo): string => $pdo->query('SHOW CREATE TABLE '.$identifier)->fetch(PDO::FETCH_NUM)[1];
    if ($definition($source) !== $definition($target)) {
        throw new RuntimeException('Exact table definition differs for '.$table);
    }
    $total += $expected[0];
    printf("PASS %-24s %5d records %s\n", $table, $expected[0], $expected[1]);
}
$triggers = static function (PDO $pdo): array {
    return $pdo->query('SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_STATEMENT, ACTION_TIMING FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() ORDER BY TRIGGER_NAME')->fetchAll();
};
if ($triggers($source) !== $triggers($target)) {
    throw new RuntimeException('Trigger definitions differ.');
}
printf("PASS %d tables, %d records, all column values/schema/auto-increments and %d triggers match exactly.\n", count($sourceTables), $total, count($triggers($source)));
