<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000024CreateProductFactories extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000024;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_factory` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.jv_factory.name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_factory_source` (
                `id` BINARY(16) NOT NULL,
                `source_namespace` VARCHAR(128) NOT NULL,
                `external_id` VARCHAR(64) NOT NULL,
                `factory_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.jv_factory_source.identity` (`source_namespace`, `external_id`),
                KEY `idx.jv_factory_source.factory` (`factory_id`),
                CONSTRAINT `fk.jv_factory_source.factory` FOREIGN KEY (`factory_id`) REFERENCES `jv_factory` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        if (0 === (int) $connection->fetchOne("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product' AND COLUMN_NAME = 'jv_factory_id'")) {
            $connection->executeStatement('ALTER TABLE `product` ADD COLUMN `jv_factory_id` BINARY(16) NULL');
        }
        if (0 === (int) $connection->fetchOne("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product' AND INDEX_NAME = 'idx.product.jv_factory_id'")) {
            $connection->executeStatement('ALTER TABLE `product` ADD KEY `idx.product.jv_factory_id` (`jv_factory_id`)');
        }
        if (0 === (int) $connection->fetchOne("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'product' AND CONSTRAINT_NAME = 'fk.product.jv_factory_id'")) {
            $connection->executeStatement('ALTER TABLE `product` ADD CONSTRAINT `fk.product.jv_factory_id` FOREIGN KEY (`jv_factory_id`) REFERENCES `jv_factory` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
