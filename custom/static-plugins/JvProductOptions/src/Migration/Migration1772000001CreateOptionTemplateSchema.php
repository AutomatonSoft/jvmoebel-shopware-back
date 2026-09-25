<?php declare(strict_types=1);

namespace Jv\ProductOptions\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1772000001CreateOptionTemplateSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772000001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `jv_option_template` (
                `id` BINARY(16) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `priority` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `jv_option_template_translation` (
                `jv_option_template_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`jv_option_template_id`, `language_id`),
                CONSTRAINT `fk.jv_option_template_translation.template_id`
                    FOREIGN KEY (`jv_option_template_id`) REFERENCES `jv_option_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_option_template_translation.language_id`
                    FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `jv_option_template_group` (
                `id` BINARY(16) NOT NULL,
                `template_id` BINARY(16) NOT NULL,
                `position` INT NOT NULL DEFAULT 0,
                `default_value_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.jv_option_template_group.template_id`
                    FOREIGN KEY (`template_id`) REFERENCES `jv_option_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `jv_option_template_group_translation` (
                `jv_option_template_group_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`jv_option_template_group_id`, `language_id`),
                CONSTRAINT `fk.jv_option_template_group_translation.group_id`
                    FOREIGN KEY (`jv_option_template_group_id`) REFERENCES `jv_option_template_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_option_template_group_translation.language_id`
                    FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `jv_option_template_value` (
                `id` BINARY(16) NOT NULL,
                `group_id` BINARY(16) NOT NULL,
                `position` INT NOT NULL DEFAULT 0,
                `color_hex` VARCHAR(7) NULL,
                `media_id` BINARY(16) NULL,
                `surcharge_type` VARCHAR(32) NOT NULL,
                `surcharge_price` JSON NULL,
                `surcharge_percentage` DOUBLE NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.jv_option_template_value.group_id`
                    FOREIGN KEY (`group_id`) REFERENCES `jv_option_template_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_option_template_value.media_id`
                    FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `jv_option_template_value_translation` (
                `jv_option_template_value_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`jv_option_template_value_id`, `language_id`),
                CONSTRAINT `fk.jv_option_template_value_translation.value_id`
                    FOREIGN KEY (`jv_option_template_value_id`) REFERENCES `jv_option_template_value` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_option_template_value_translation.language_id`
                    FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            ALTER TABLE `jv_option_template_group`
                ADD CONSTRAINT `fk.jv_option_template_group.default_value_id`
                    FOREIGN KEY (`default_value_id`) REFERENCES `jv_option_template_value` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

            CREATE TABLE IF NOT EXISTS `jv_option_template_product_stream` (
                `template_id` BINARY(16) NOT NULL,
                `product_stream_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`template_id`, `product_stream_id`),
                CONSTRAINT `fk.jv_option_template_product_stream.template_id`
                    FOREIGN KEY (`template_id`) REFERENCES `jv_option_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_option_template_product_stream.product_stream_id`
                    FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `jv_option_template_product` (
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `template_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`product_id`, `product_version_id`),
                CONSTRAINT `fk.jv_option_template_product.product`
                    FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_option_template_product.template_id`
                    FOREIGN KEY (`template_id`) REFERENCES `jv_option_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
