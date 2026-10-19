<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** The original RFQ form accepts Commercial; older databases omitted it from the enum. */
final class Version20261019000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow the existing Commercial RFQ facility choice while preserving all other enum choices';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $columnType = $this->connection->fetchOne(<<<'SQL'
SELECT COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'commercial_leads'
  AND COLUMN_NAME = 'facility_type'
SQL);

        if (!is_string($columnType) || !str_starts_with(strtolower($columnType), 'enum(')) {
            throw new \RuntimeException('commercial_leads.facility_type must be an enum.');
        }

        if (!str_contains($columnType, "'Commercial'")) {
            $expanded = substr($columnType, 0, -1).",'Commercial')";
            $this->addSql('ALTER TABLE commercial_leads MODIFY facility_type '.$expanded.' NULL DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException('Removing a permitted RFQ value could invalidate existing customer data.');
    }
}
