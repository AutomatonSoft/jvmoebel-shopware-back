<?php declare(strict_types=1);

namespace Jv\Promotion\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1772000001CreateJvPromotionTargetSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772000001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_promotion_target` (
                `id` BINARY(16) NOT NULL,
                `promotion_id` BINARY(16) NOT NULL,
                `target_type` VARCHAR(32) NOT NULL,
                `factory_id` INT NULL,
                `stammartikel_id` VARCHAR(64) NULL,
                `source_file_prefix` VARCHAR(128) NULL,
                `product_id` BINARY(16) NULL,
                `product_version_id` BINARY(16) NULL,
                `ean` VARCHAR(64) NULL,
                `discount_percent` DOUBLE NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.jv_promotion_target.promotion` (`promotion_id`),
                KEY `idx.jv_promotion_target.factory` (`factory_id`),
                KEY `idx.jv_promotion_target.factory_collection` (`factory_id`, `stammartikel_id`),
                KEY `idx.jv_promotion_target.prefix` (`source_file_prefix`),
                KEY `idx.jv_promotion_target.product` (`product_id`, `product_version_id`),
                KEY `idx.jv_promotion_target.ean` (`ean`),
                CONSTRAINT `fk.jv_promotion_target.promotion`
                    FOREIGN KEY (`promotion_id`) REFERENCES `promotion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }
}
