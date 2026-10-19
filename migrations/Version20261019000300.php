<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Restore indexes present in the canonical schema but missing in the original export. */
final class Version20261019000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing cart-date and lead-type indexes without modifying records';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        foreach ([
            ['carts', 'idx_carts_created_at', 'created_at'],
            ['commercial_leads', 'idx_commercial_leads_type', 'lead_type'],
        ] as [$table, $index, $column]) {
            $exists = $this->connection->fetchOne(
                'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
                [$table, $index]
            );
            if ($exists === false) {
                $this->addSql(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (`%s`)', $table, $index, $column));
            }
        }
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException('These indexes may now be in use and are not removed automatically.');
    }
}
