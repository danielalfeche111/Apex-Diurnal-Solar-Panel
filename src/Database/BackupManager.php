<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/** Portable logical backups, including data, keys, triggers and extra database objects. */
final class BackupManager
{
    private const HEADER = '-- Apex Diurnal SQL backup v1';

    public function __construct(private DatabaseConnection $database)
    {
    }

    /** @return array<string, int> */
    public function backup(string $filename): array
    {
        $pdo = $this->database->getConnection();
        $handle = @fopen($filename, 'xb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot create backup; the parent directory must exist and the file must not already exist.');
        }
        @chmod($filename, 0600);
        $write = static function (string $text) use ($handle): void {
            $remaining = $text;
            while ($remaining !== '') {
                $written = fwrite($handle, $remaining);
                if ($written === false || $written === 0) {
                    throw new \RuntimeException('Writing database backup failed.');
                }
                $remaining = substr($remaining, $written);
            }
        };
        $counts = [];
        try {
            $write(self::HEADER."\n-- Contains private customer data. Keep outside the public directory and version control.\n-- Restore only into an empty database with app:database:restore.\n\nSET NAMES utf8mb4;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->beginTransaction();
            $objects = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
            $views = [];
            foreach ($objects as [$table, $type]) {
                if ($type === 'VIEW') {
                    $views[] = $table;
                    continue;
                }
                $identifier = DatabaseConnection::identifier($table);
                $definition = $pdo->query('SHOW CREATE TABLE '.$identifier)->fetch(PDO::FETCH_NUM)[1];
                $write($definition.";\n");
                $columns = $pdo->query('SHOW COLUMNS FROM '.$identifier)->fetchAll();
                // Generated columns must not be assigned during restore.
                $insertColumns = array_values(array_map(static fn (array $column): string => $column['Field'], array_filter($columns, static fn (array $column): bool => !preg_match('/(?:VIRTUAL|STORED) GENERATED/i', $column['Extra']))));
                $columnSql = implode(', ', array_map([DatabaseConnection::class, 'identifier'], $insertColumns));
                $rows = $pdo->query('SELECT '.$columnSql.' FROM '.$identifier);
                $counts[$table] = 0;
                while ($row = $rows->fetch(PDO::FETCH_NUM)) {
                    $values = array_map(static fn (mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), $row);
                    $write('INSERT INTO '.$identifier.' ('.$columnSql.') VALUES ('.implode(', ', $values).");\n");
                    ++$counts[$table];
                }
                $write("\n");
            }
            foreach ($views as $view) {
                $definition = $pdo->query('SHOW CREATE VIEW '.DatabaseConnection::identifier($view))->fetch(PDO::FETCH_NUM)[1];
                $write($this->withoutDefiner($definition).";\n\n");
            }
            $triggers = $pdo->query('SHOW TRIGGERS')->fetchAll();
            foreach ($triggers as $trigger) {
                $definition = $pdo->query('SHOW CREATE TRIGGER '.DatabaseConnection::identifier($trigger['Trigger']))->fetch();
                $write("DELIMITER //\n".$this->withoutDefiner($definition['SQL Original Statement'])."//\nDELIMITER ;\n\n");
            }
            $routineQuery = $pdo->query('SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()');
            foreach ($routineQuery->fetchAll() as $routine) {
                $type = $routine['ROUTINE_TYPE'];
                $definition = $pdo->query('SHOW CREATE '.$type.' '.DatabaseConnection::identifier($routine['ROUTINE_NAME']))->fetch();
                $create = $definition['Create '.ucfirst(strtolower($type))];
                if (!is_string($create)) {
                    throw new \RuntimeException('Insufficient permissions to back up a database routine.');
                }
                $write("DELIMITER //\n".$this->withoutDefiner($create)."//\nDELIMITER ;\n\n");
            }
            foreach ($pdo->query('SHOW EVENTS')->fetchAll() as $event) {
                $definition = $pdo->query('SHOW CREATE EVENT '.DatabaseConnection::identifier($event['Name']))->fetch();
                $write("DELIMITER //\n".$this->withoutDefiner($definition['Create Event'])."//\nDELIMITER ;\n\n");
            }
            $write("SET FOREIGN_KEY_CHECKS = 1;\n-- End Apex Diurnal SQL backup v1\n");
            $pdo->commit();
            fclose($handle);

            return $counts;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fclose($handle);
            // An incomplete backup must never appear to be restorable.
            @unlink($filename);
            throw $exception;
        }
    }

    /** @return array<string, int> */
    public function restore(string $filename): array
    {
        $sql = @file_get_contents($filename);
        if ($sql === false || !str_starts_with($sql, self::HEADER."\n") || !str_ends_with(trim($sql), '-- End Apex Diurnal SQL backup v1')) {
            throw new \RuntimeException('Use a backup created by app:database:backup. Native mysqldump files can be restored with the mysql client.');
        }
        $pdo = $this->database->ensureDatabase();
        $routines = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()')->fetchColumn();
        if ($pdo->query('SHOW TABLES')->fetchAll() !== [] || $pdo->query('SHOW EVENTS')->fetchAll() !== [] || $routines !== 0) {
            throw new \RuntimeException('Restore requires an empty database. Select a NEW database in DATABASE_URL; existing data is never overwritten.');
        }
        $statements = SqlScript::statements($sql);
        foreach ($statements as $statement) {
            if (preg_match('/^(?:USE\s|(?:CREATE|DROP|ALTER)\s+DATABASE\s)/i', $statement)) {
                throw new \RuntimeException('A portable backup must not switch or alter databases.');
            }
        }
        try {
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        $counts = [];
        foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$table, $type]) {
            if ($type !== 'VIEW') {
                $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM '.DatabaseConnection::identifier($table))->fetchColumn();
            }
        }

        return $counts;
    }

    private function withoutDefiner(string $sql): string
    {
        // Destination credentials own restored objects; source server accounts need not exist.
        return preg_replace('/\bDEFINER\s*=\s*`(?:[^`]|``)*`@`(?:[^`]|``)*`\s*/i', '', $sql);
    }
}
