<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000018CreateAfterCoolImportStorage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000018;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `jv_aftercool_import_run` (`id` BINARY(16) NOT NULL,`account` VARCHAR(16) NOT NULL,`dataset` VARCHAR(32) NOT NULL,`factory_id` INT NOT NULL,`factory_name` VARCHAR(255) NOT NULL,`status` VARCHAR(32) NOT NULL,`total` INT NULL,`next_offset` INT NOT NULL,`processed` INT NOT NULL,`created` INT NOT NULL,`updated` INT NOT NULL,`skipped` INT NOT NULL,`failed` INT NOT NULL,`active_factory_key` VARCHAR(128) NULL,`started_at` DATETIME(3) NULL,`finished_at` DATETIME(3) NULL,`safe_failure_code` VARCHAR(128) NULL,`safe_failure_message` VARCHAR(255) NULL,`created_at` DATETIME(3) NOT NULL,`updated_at` DATETIME(3) NULL,PRIMARY KEY (`id`),UNIQUE KEY `uniq.jv_aftercool_import_run.active_factory` (`active_factory_key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `jv_aftercool_product_source` (`id` BINARY(16) NOT NULL,`account` VARCHAR(16) NOT NULL,`dataset` VARCHAR(32) NOT NULL,`factory_id` INT NOT NULL,`source_product_id` VARCHAR(64) NOT NULL,`product_id` BINARY(16) NOT NULL,`product_version_id` BINARY(16) NOT NULL,`source_artikelnummer` VARCHAR(255) NOT NULL,`source_ean` VARCHAR(32) NOT NULL,`last_seen_at` DATETIME(3) NOT NULL,`created_at` DATETIME(3) NOT NULL,`updated_at` DATETIME(3) NULL,PRIMARY KEY (`id`),UNIQUE KEY `uniq.jv_aftercool_product_source.identity` (`account`,`dataset`,`factory_id`,`source_product_id`),CONSTRAINT `fk.jv_aftercool_product_source.product` FOREIGN KEY (`product_id`,`product_version_id`) REFERENCES `product` (`id`,`version_id`) ON DELETE RESTRICT ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `jv_aftercool_import_error` (`id` BINARY(16) NOT NULL,`run_id` BINARY(16) NOT NULL,`factory_id` INT NOT NULL,`product_id` VARCHAR(64) NULL,`artikelnummer` VARCHAR(255) NULL,`ean` VARCHAR(32) NULL,`offset` INT NOT NULL,`row_no` INT NULL,`result` VARCHAR(32) NOT NULL,`code` VARCHAR(128) NOT NULL,`message` VARCHAR(255) NOT NULL,`created_at` DATETIME(3) NOT NULL,`updated_at` DATETIME(3) NULL,PRIMARY KEY (`id`),CONSTRAINT `fk.jv_aftercool_import_error.run` FOREIGN KEY (`run_id`) REFERENCES `jv_aftercool_import_run` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
