<?php declare(strict_types=1);

namespace Jv\Seo\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1789344000CreateRedirectSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1789344000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_seo_redirect` (
                `id` BINARY(16) NOT NULL,
                `type` VARCHAR(32) NOT NULL,
                `product_id` BINARY(16) NULL,
                `product_version_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.jv_seo_redirect.product` (`type`, `product_id`, `product_version_id`),
                KEY `idx.jv_seo_redirect.type` (`type`),
                CONSTRAINT `fk.jv_seo_redirect.product` FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_seo_redirect_channel` (
                `id` BINARY(16) NOT NULL,
                `redirect_id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `target_url` VARCHAR(2048) NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `manually_modified` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.jv_seo_redirect_channel.redirect_channel` (`redirect_id`, `sales_channel_id`),
                KEY `idx.jv_seo_redirect_channel.sales_channel` (`sales_channel_id`, `active`),
                CONSTRAINT `fk.jv_seo_redirect_channel.redirect` FOREIGN KEY (`redirect_id`)
                    REFERENCES `jv_seo_redirect` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_seo_redirect_channel.sales_channel` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_seo_redirect_source` (
                `id` BINARY(16) NOT NULL,
                `redirect_channel_id` BINARY(16) NOT NULL,
                `source_url` VARCHAR(2048) NOT NULL,
                `source_url_hash` CHAR(64) NOT NULL,
                `active_source_url_hash` CHAR(64) NULL,
                `origin` VARCHAR(32) NOT NULL,
                `source_system` VARCHAR(64) NULL,
                `source_market` VARCHAR(255) NULL,
                `source_identifier` VARCHAR(255) NULL,
                `import_key_hash` CHAR(64) NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `manually_modified` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.jv_seo_redirect_source.active_url` (`active_source_url_hash`),
                UNIQUE KEY `uniq.jv_seo_redirect_source.import_key` (`import_key_hash`),
                KEY `idx.jv_seo_redirect_source.channel_active` (`redirect_channel_id`, `active`),
                KEY `idx.jv_seo_redirect_source.source_url` (`source_url`(191)),
                CONSTRAINT `fk.jv_seo_redirect_source.channel` FOREIGN KEY (`redirect_channel_id`)
                    REFERENCES `jv_seo_redirect_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
