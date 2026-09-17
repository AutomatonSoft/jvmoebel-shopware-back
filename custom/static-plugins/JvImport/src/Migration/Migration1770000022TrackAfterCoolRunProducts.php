<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000022TrackAfterCoolRunProducts extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000022;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->createSchemaManager()->listTableColumns('jv_aftercool_import_run');
        if (!isset($columns['enrichment_queued_at'])) {
            $connection->executeStatement('ALTER TABLE `jv_aftercool_import_run` ADD COLUMN `enrichment_queued_at` DATETIME(3) NULL AFTER `finished_at`');
        }

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_aftercool_import_run_product` (
                `id` BINARY(16) NOT NULL,
                `run_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `product_number` VARCHAR(255) NOT NULL,
                `source_ean` VARCHAR(32) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.jv_aftercool_import_run_product.product` (`run_id`, `product_id`),
                KEY `idx.jv_aftercool_import_run_product.order` (`run_id`, `product_number`, `id`),
                CONSTRAINT `fk.jv_aftercool_import_run_product.run`
                    FOREIGN KEY (`run_id`) REFERENCES `jv_aftercool_import_run` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk.jv_aftercool_import_run_product.product`
                    FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
