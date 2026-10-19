<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Catalog defaults for fresh installs; existing products and stock are never replaced. */
final class Version20261019000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the original storefront catalog and opening stock only where missing';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
INSERT IGNORE INTO products (id, name, type, base_price, description, image) VALUES
('residential-arrays', 'Residential Arrays', 'physical', 8120.00, 'High-efficiency monocrystalline solar panels engineered for residential rooftops.', 'assets/images/residential arrays.png'),
('advanced-solar-inverter', 'Advanced Solar Inverter', 'physical', 25200.00, 'Pure sine-wave hybrid smart inverter with 98.4% peak grid conversion efficiency.', 'assets/images/advance power inverter.png'),
('commercial-grids', 'Commercial Grids', 'custom', 0.00, 'Utility-scale commercial solar panel grid installations for industrial and corporate facilities.', 'assets/images/commercial grids.png'),
('installation-booking', 'Professional Installation Booking', 'service', 8400.00, 'Certified master technician site assessment, 3D solar layout modeling & turnkey mounting.', 'assets/images/product-booking.png')
SQL);

        $this->addSql(<<<'SQL'
INSERT IGNORE INTO inventory (product_id, sku, current_stock, min_stock_level, max_stock_level, reorder_point) VALUES
('residential-arrays', 'APX-MOD-RES01', 45, 5, 100, 10),
('advanced-solar-inverter', 'APX-INV-SMT02', 28, 5, 50, 8)
SQL);
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException('Catalog migration cannot be reversed without risking existing order and stock data.');
    }
}
