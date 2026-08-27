<?php declare(strict_types=1);

namespace Jv\LeadManagement\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1787826538CreateLeadAndContact extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787826538;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_lead_management_lead` (
                `id` BINARY(16) NOT NULL,
                `visitor_id` CHAR(36) NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `domain` VARCHAR(255) NOT NULL,
                `market_code` VARCHAR(16) NOT NULL,
                `first_contact_channel` VARCHAR(32) NOT NULL,
                `contact_type` VARCHAR(64) NOT NULL,
                `name` VARCHAR(255) NULL,
                `email` VARCHAR(255) NULL,
                `phone` VARCHAR(64) NULL,
                `message` LONGTEXT NULL,
                `status` VARCHAR(32) NOT NULL,
                `customer_id` BINARY(16) NULL,
                `order_id` BINARY(16) NULL,
                `order_version_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,

                PRIMARY KEY (`id`),

                UNIQUE KEY `uniq.jv_lead_management_lead.order`
                    (`order_id`, `order_version_id`),

                KEY `idx.jv_lead_management_lead.visitor`
                    (`visitor_id`),

                KEY `idx.jv_lead_management_lead.sales_channel_status`
                    (`sales_channel_id`, `status`),

                KEY `idx.jv_lead_management_lead.email`
                    (`email`),

                KEY `idx.jv_lead_management_lead.phone`
                    (`phone`),

                CONSTRAINT `fk.jv_lead_management_lead.sales_channel`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT `fk.jv_lead_management_lead.customer`
                    FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,

                CONSTRAINT `fk.jv_lead_management_lead.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_lead_management_contact` (
                `id` BINARY(16) NOT NULL,
                `lead_id` BINARY(16) NOT NULL,
                `visitor_id` CHAR(36) NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `contact_channel` VARCHAR(32) NOT NULL,
                `contact_type` VARCHAR(64) NOT NULL,
                `tracking_reference` VARCHAR(128) NULL,
                `provider` VARCHAR(64) NULL,
                `provider_reference` VARCHAR(255) NULL,
                `product_number` VARCHAR(255) NULL,
                `name` VARCHAR(255) NULL,
                `email` VARCHAR(255) NULL,
                `phone` VARCHAR(64) NULL,
                `message` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,

                PRIMARY KEY (`id`),

                KEY `idx.jv_lead_management_contact.lead`
                    (`lead_id`),

                KEY `idx.jv_lead_management_contact.visitor`
                    (`visitor_id`),

                KEY `idx.jv_lead_management_contact.tracking_reference`
                    (`tracking_reference`),

                UNIQUE KEY `uniq.jv_lead_management_contact.provider_reference`
                    (`provider`, `provider_reference`),

                CONSTRAINT `fk.jv_lead_management_contact.lead`
                    FOREIGN KEY (`lead_id`)
                    REFERENCES `jv_lead_management_lead` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,

                CONSTRAINT `fk.jv_lead_management_contact.sales_channel`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
