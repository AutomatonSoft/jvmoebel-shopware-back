<?php declare(strict_types=1);

namespace Jv\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1771000004CreateShippingBadgeSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1771000004;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_storefront_shipping_badge` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NULL,
                `icon_media_id` BINARY(16) NOT NULL,
                `position` INT NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.jv_storefront_shipping_badge.sales_channel` (`sales_channel_id`, `active`, `position`),
                CONSTRAINT `fk.jv_storefront_shipping_badge.sales_channel`
                    FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_storefront_shipping_badge.media`
                    FOREIGN KEY (`icon_media_id`) REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
