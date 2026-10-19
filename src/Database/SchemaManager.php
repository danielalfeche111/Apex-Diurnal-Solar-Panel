<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class SchemaManager
{
    public function __construct(private DatabaseConnection $database, private string $projectDir)
    {
    }

    /** @return list<string> Completed operations, safe to display without customer data. */
    public function install(bool $seedNewDatabase = true): array
    {
        $pdo = $this->database->ensureDatabase();
        $empty = $pdo->query('SHOW TABLES')->fetchAll() === [];
        $operations = [];
        foreach ($this->definitions() as $table => $definition) {
            $pdo->exec($definition['sql']);
            $columns = $this->columns($pdo, $table);
            foreach ($definition['columns'] as $column => $sql) {
                if (!isset($columns[$column])) {
                    $pdo->exec('ALTER TABLE '.DatabaseConnection::identifier($table).' ADD COLUMN '.$sql);
                    $operations[] = 'Added '.$table.'.'.$column;
                    continue;
                }
                $actual = $columns[$column];
                // Expand enums without reordering existing values or dropping custom values.
                if (preg_match('/^\w+\s+ENUM\((.*?)\)/is', $sql, $matches) && str_starts_with(strtolower($actual['COLUMN_TYPE']), 'enum(')) {
                    preg_match_all("/'(?:[^'\\\\]|\\\\.|'')*'/", $matches[1], $expected);
                    preg_match_all("/'(?:[^'\\\\]|\\\\.|'')*'/", $actual['COLUMN_TYPE'], $existing);
                    $missing = array_diff($expected[0], $existing[0]);
                    if ($missing !== []) {
                        $type = 'ENUM('.implode(',', array_merge($existing[0], array_values($missing))).')';
                        $this->modifyColumn($pdo, $table, $column, $type, $actual, $actual['IS_NULLABLE'] === 'YES');
                        $actual['COLUMN_TYPE'] = $type;
                        $operations[] = 'Expanded '.$table.'.'.$column.' values';
                    }
                }
                // The real site uses two-hour appointment slots; preserve both old and new text.
                if (in_array($column, ['preferred_time_slot', 'best_call_time'], true) && str_starts_with(strtolower($actual['COLUMN_TYPE']), 'enum(')) {
                    $nullable = !str_contains(strtoupper($sql), 'NOT NULL');
                    $this->modifyColumn($pdo, $table, $column, 'VARCHAR(50)', $actual, $nullable);
                    $actual['COLUMN_TYPE'] = 'VARCHAR(50)';
                    $actual['IS_NULLABLE'] = $nullable ? 'YES' : 'NO';
                    $operations[] = 'Expanded '.$table.'.'.$column.' to support existing appointment choices';
                }
                if ($table === 'commercial_leads' && in_array($column, ['facility_type', 'power_supply', 'best_call_time', 'preferred_date', 'preferred_time_slot'], true) && $actual['IS_NULLABLE'] === 'NO') {
                    $type = in_array($column, ['preferred_time_slot', 'best_call_time'], true) ? 'VARCHAR(50)' : $actual['COLUMN_TYPE'];
                    $this->modifyColumn($pdo, $table, $column, $type, $actual, true);
                    $operations[] = 'Allowed optional RFQ field '.$table.'.'.$column;
                }
            }
            $indexes = $pdo->query('SHOW INDEX FROM '.DatabaseConnection::identifier($table))->fetchAll();
            $indexNames = array_column($indexes, 'Key_name');
            foreach ($definition['indexes'] as $name => $sql) {
                if (!in_array($name, $indexNames, true)) {
                    $pdo->exec('ALTER TABLE '.DatabaseConnection::identifier($table).' ADD '.$sql);
                    $operations[] = 'Added index '.$table.'.'.$name;
                }
            }
            $query = $pdo->prepare('SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL');
            $query->execute([$table]);
            $foreignKeys = [];
            foreach ($query as $key) {
                $foreignKeys[] = $key['COLUMN_NAME'].':'.$key['REFERENCED_TABLE_NAME'].':'.$key['REFERENCED_COLUMN_NAME'];
            }
            foreach ($definition['foreign_keys'] as $signature => $sql) {
                if (!in_array($signature, $foreignKeys, true)) {
                    $pdo->exec('ALTER TABLE '.DatabaseConnection::identifier($table).' ADD '.$sql);
                    $operations[] = 'Added relationship '.$table.'.'.$signature;
                }
            }
        }
        if ($empty && $seedNewDatabase) {
            foreach ($this->script('seeds.sql') as $sql) {
                $pdo->exec($sql);
            }
            $operations[] = 'Installed original catalog, inventory and development account seed values';
        }
        $query = $pdo->query('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()');
        $existingTriggers = $query->fetchAll(PDO::FETCH_COLUMN);
        foreach ($this->script('triggers.sql') as $sql) {
            preg_match('/CREATE TRIGGER\s+(\w+)/i', $sql, $matches);
            if (!in_array($matches[1], $existingTriggers, true)) {
                $pdo->exec($sql);
                $operations[] = 'Added trigger '.$matches[1];
            }
        }

        return $operations;
    }

    /** @return array{tables: array<string, int>, problems: list<string>} */
    public function check(): array
    {
        $pdo = $this->database->getConnection();
        $present = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $counts = [];
        $problems = [];
        foreach ($this->definitions() as $table => $definition) {
            if (!in_array($table, $present, true)) {
                $problems[] = 'Missing table '.$table;
                continue;
            }
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM '.DatabaseConnection::identifier($table))->fetchColumn();
            $columns = $this->columns($pdo, $table);
            foreach ($definition['columns'] as $column => $columnSql) {
                if (!isset($columns[$column])) {
                    $problems[] = 'Missing column '.$table.'.'.$column;
                    continue;
                }
                if (preg_match('/^\w+\s+ENUM\((.*?)\)/is', $columnSql, $matches) && str_starts_with(strtolower($columns[$column]['COLUMN_TYPE']), 'enum(')) {
                    preg_match_all("/'(?:[^'\\\\]|\\\\.|'')*'/", $matches[1], $expected);
                    preg_match_all("/'(?:[^'\\\\]|\\\\.|'')*'/", $columns[$column]['COLUMN_TYPE'], $actual);
                    $missing = array_diff($expected[0], $actual[0]);
                    if ($missing !== []) {
                        $problems[] = 'Missing allowed values for '.$table.'.'.$column.': '.implode(', ', $missing);
                    }
                }
            }
            foreach (['preferred_time_slot', 'best_call_time'] as $column) {
                if (isset($columns[$column]) && str_starts_with(strtolower($columns[$column]['COLUMN_TYPE']), 'enum(')) {
                    $problems[] = 'Appointment field needs expansion: '.$table.'.'.$column;
                }
            }
        }
        $triggers = $pdo->query('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
        foreach (['order_status_insert', 'order_status_update'] as $trigger) {
            if (!in_array($trigger, $triggers, true)) {
                $problems[] = 'Missing trigger '.$trigger;
            }
        }

        return ['tables' => $counts, 'problems' => $problems];
    }

    private function modifyColumn(PDO $pdo, string $table, string $column, string $type, array $actual, bool $nullable): void
    {
        $default = $actual['COLUMN_DEFAULT'];
        // MariaDB quotes defaults in information_schema; MySQL returns the plain value.
        if (is_string($default) && strlen($default) >= 2 && $default[0] === "'" && str_ends_with($default, "'")) {
            $default = str_replace("''", "'", substr($default, 1, -1));
        }
        $sql = 'ALTER TABLE '.DatabaseConnection::identifier($table).' MODIFY '.DatabaseConnection::identifier($column).' '.$type.($nullable ? ' NULL' : ' NOT NULL');
        if ($default !== null && strtoupper((string) $default) !== 'NULL') {
            $sql .= ' DEFAULT '.$pdo->quote((string) $default);
        } elseif ($nullable) {
            $sql .= ' DEFAULT NULL';
        }
        if ($actual['COLUMN_COMMENT'] !== '') {
            $sql .= ' COMMENT '.$pdo->quote($actual['COLUMN_COMMENT']);
        }
        $pdo->exec($sql);
    }

    private function columns(PDO $pdo, string $table): array
    {
        $query = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $query->execute([$table]);
        $columns = [];
        foreach ($query as $column) {
            $columns[$column['COLUMN_NAME']] = $column;
        }

        return $columns;
    }

    private function definitions(): array
    {
        $tables = [];
        foreach ($this->script('schema.sql') as $sql) {
            if (!preg_match('/^CREATE TABLE IF NOT EXISTS (\w+)\s*\((.*)\) ENGINE=/s', $sql, $match)) {
                throw new \RuntimeException('Invalid schema definition.');
            }
            $definition = ['sql' => $sql, 'columns' => [], 'indexes' => [], 'foreign_keys' => []];
            foreach (preg_split('/\R/', trim($match[2])) as $line) {
                $line = rtrim(trim($line), ',');
                if (preg_match('/^(?:UNIQUE )?KEY\s+(\w+)/', $line, $key)) {
                    $definition['indexes'][$key[1]] = $line;
                } elseif (preg_match('/^FOREIGN KEY \((\w+)\) REFERENCES (\w+)\((\w+)\)/', $line, $key)) {
                    $definition['foreign_keys'][$key[1].':'.$key[2].':'.$key[3]] = $line;
                } elseif (preg_match('/^(\w+)\s+/', $line, $column)) {
                    $definition['columns'][$column[1]] = $line;
                    if (str_contains($line, 'PRIMARY KEY')) {
                        $definition['indexes']['PRIMARY'] = 'PRIMARY KEY ('.DatabaseConnection::identifier($column[1]).')';
                    } elseif (preg_match('/\bUNIQUE\b/', $line)) {
                        $definition['indexes'][$column[1]] = 'UNIQUE KEY '.DatabaseConnection::identifier($column[1]).' ('.DatabaseConnection::identifier($column[1]).')';
                    }
                }
            }
            $tables[$match[1]] = $definition;
        }

        return $tables;
    }

    private function script(string $name): array
    {
        $sql = file_get_contents($this->projectDir.'/database/'.$name);
        if ($sql === false) {
            throw new \RuntimeException('Cannot read database script '.$name);
        }

        return SqlScript::statements($sql);
    }
}
