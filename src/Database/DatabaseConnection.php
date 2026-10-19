<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

/** PDO connection for the backup and schema inspection console commands. */
final class DatabaseConnection
{
    private ?PDO $connection = null;
    private array $options;

    public function __construct(string $databaseUrl)
    {
        $parts = parse_url($databaseUrl);
        if ($parts === false || !in_array($parts['scheme'] ?? '', ['mysql', 'mariadb'], true)) {
            throw new \InvalidArgumentException('DATABASE_URL must be a mysql:// or mariadb:// URL.');
        }
        parse_str($parts['query'] ?? '', $query);
        $this->options = [
            'host' => $parts['host'] ?? '127.0.0.1',
            'port' => (int) ($parts['port'] ?? 3306),
            'database' => rawurldecode(ltrim($parts['path'] ?? '', '/')),
            'user' => rawurldecode($parts['user'] ?? ''),
            'password' => rawurldecode($parts['pass'] ?? ''),
            'charset' => $query['charset'] ?? 'utf8mb4',
            'socket' => $query['unix_socket'] ?? null,
        ];
        if ($this->options['database'] === '' || preg_match('/[;\x00-\x1f]/', $this->options['database'])) {
            throw new \InvalidArgumentException('DATABASE_URL must include a valid database name.');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $this->options['charset'])) {
            throw new \InvalidArgumentException('Invalid database character set.');
        }
        foreach (['host', 'socket'] as $key) {
            if ($this->options[$key] !== null && preg_match('/[;\x00-\x1f]/', $this->options[$key])) {
                throw new \InvalidArgumentException('Invalid database server address.');
            }
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection ??= $this->connect(true);
    }

    public function getDatabaseName(): string
    {
        return $this->options['database'];
    }

    public function ensureDatabase(): PDO
    {
        try {
            return $this->getConnection();
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1049) {
                throw $exception;
            }
            $this->connect(false)->exec('CREATE DATABASE '.self::identifier($this->getDatabaseName()).' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');

            return $this->getConnection();
        }
    }

    public static function identifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }

    private function connect(bool $withDatabase): PDO
    {
        $dsn = $this->options['socket'] !== null
            ? 'mysql:unix_socket='.$this->options['socket']
            : 'mysql:host='.$this->options['host'].';port='.$this->options['port'];
        if ($withDatabase) {
            $dsn .= ';dbname='.$this->getDatabaseName();
        }

        return new PDO($dsn.';charset='.$this->options['charset'], $this->options['user'], $this->options['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ]);
    }
}
