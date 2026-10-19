<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Database\SqlScript;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Baseline of the original site's complete 14-table schema and order history triggers. */
final class Version20261019000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the complete existing storefront database without replacing customer data';
    }

    public function isTransactional(): bool
    {
        // MySQL commits DDL independently of a surrounding transaction.
        return false;
    }

    public function up(Schema $schema): void
    {
        foreach ($this->readStatements('schema.sql') as $statement) {
            $this->addSql($statement);
        }

        $triggers = $this->connection->fetchFirstColumn(
            'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()'
        );
        foreach ($this->readStatements('triggers.sql') as $statement) {
            if (!preg_match('/CREATE TRIGGER\s+(\w+)/i', $statement, $matches)) {
                throw new \RuntimeException('Invalid trigger definition in database/triggers.sql.');
            }
            if (!in_array($matches[1], $triggers, true)) {
                $this->addSql($statement);
            }
        }
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException('This baseline migration cannot be reversed without deleting customer data.');
    }

    /** @return list<string> */
    private function readStatements(string $file): array
    {
        $path = dirname(__DIR__).'/database/'.$file;
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException('Missing migration source '.$path);
        }

        return SqlScript::statements($sql);
    }
}
