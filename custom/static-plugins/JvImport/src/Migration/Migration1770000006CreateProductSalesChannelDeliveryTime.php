<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000006CreateProductSalesChannelDeliveryTime extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000006;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_import_product_sales_channel_delivery_time` (
                `id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `delivery_time_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.jv_import_product_delivery_time.product_channel` (`product_id`, `product_version_id`, `sales_channel_id`),
                CONSTRAINT `fk.jv_import_product_delivery_time.product` FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_import_product_delivery_time.sales_channel` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_import_product_delivery_time.delivery_time` FOREIGN KEY (`delivery_time_id`)
                    REFERENCES `delivery_time` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
