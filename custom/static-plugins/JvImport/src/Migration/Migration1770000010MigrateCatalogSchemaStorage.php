<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000010MigrateCatalogSchemaStorage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000010;
    }

    public function update(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        $legacyTable = 'jv_import_okb_category_group_attribute';
        $catalogTable = 'jv_catalog_category_attribute';
        if (!$schemaManager->tablesExist([$legacyTable])) {
            return;
        }
        if (!$schemaManager->tablesExist([$catalogTable])) {
            $connection->executeStatement(sprintf('RENAME TABLE `%s` TO `%s`', $legacyTable, $catalogTable));
            $connection->executeStatement(sprintf('ALTER TABLE `%s` ADD `source_code` VARCHAR(64) NOT NULL DEFAULT \'okb\' AFTER `id`', $catalogTable));
            $connection->executeStatement(sprintf('ALTER TABLE `%s` DROP INDEX `uniq.jv_import_okb_category_group_attribute.source`, ADD UNIQUE KEY `uniq.jv_catalog_category_attribute.source` (`source_code`, `category_group_id`, `attribute_id`)', $catalogTable));
        } else {
            $connection->executeStatement(<<<'SQL'
                INSERT INTO `jv_catalog_category_attribute` (
                    `id`, `source_code`, `category_group_id`, `attribute_id`, `attribute_name`, `attribute_type`,
                    `feature_relevance`, `multi_value`, `storage`, `property_group_id`, `custom_field_name`, `created_at`, `updated_at`
                )
                SELECT
                    `id`, 'okb', `category_group_id`, `attribute_id`, `attribute_name`, `attribute_type`,
                    `feature_relevance`, `multi_value`, `storage`, `property_group_id`, NULL, `created_at`, `updated_at`
                FROM `jv_import_okb_category_group_attribute`
                ON DUPLICATE KEY UPDATE
                    `attribute_name` = VALUES(`attribute_name`),
                    `attribute_type` = VALUES(`attribute_type`),
                    `feature_relevance` = VALUES(`feature_relevance`),
                    `multi_value` = VALUES(`multi_value`),
                    `storage` = VALUES(`storage`),
                    `property_group_id` = VALUES(`property_group_id`),
                    `custom_field_name` = NULL,
                    `updated_at` = NOW(3)
            SQL);
            $connection->executeStatement(sprintf('DROP TABLE `%s`', $legacyTable));
        }
        $connection->executeStatement(sprintf('UPDATE `%s` SET `custom_field_name` = NULL', $catalogTable));
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
