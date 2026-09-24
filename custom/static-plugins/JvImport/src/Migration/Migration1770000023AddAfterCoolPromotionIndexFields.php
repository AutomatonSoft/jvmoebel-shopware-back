<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000023AddAfterCoolPromotionIndexFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000023;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->createSchemaManager()->listTableColumns('jv_aftercool_product_source');

        if (!isset($columns['factory_name'])) {
            $connection->executeStatement('ALTER TABLE `jv_aftercool_product_source` ADD COLUMN `factory_name` VARCHAR(255) NULL AFTER `factory_id`');
        }
        if (!isset($columns['stammartikel_id'])) {
            $connection->executeStatement('ALTER TABLE `jv_aftercool_product_source` ADD COLUMN `stammartikel_id` VARCHAR(64) NULL AFTER `source_ean`');
        }
        if (!isset($columns['collection_name'])) {
            $connection->executeStatement('ALTER TABLE `jv_aftercool_product_source` ADD COLUMN `collection_name` VARCHAR(255) NULL AFTER `stammartikel_id`');
        }
        if (!isset($columns['source_file'])) {
            $connection->executeStatement('ALTER TABLE `jv_aftercool_product_source` ADD COLUMN `source_file` VARCHAR(255) NULL AFTER `collection_name`');
        }
        if (!isset($columns['source_file_prefix'])) {
            $connection->executeStatement('ALTER TABLE `jv_aftercool_product_source` ADD COLUMN `source_file_prefix` VARCHAR(128) NULL AFTER `source_file`');
        }
        if (!isset($columns['source_region'])) {
            $connection->executeStatement('ALTER TABLE `jv_aftercool_product_source` ADD COLUMN `source_region` VARCHAR(16) NULL AFTER `source_file_prefix`');
        }

        $indexes = $connection->createSchemaManager()->listTableIndexes('jv_aftercool_product_source');
        if (!isset($indexes['idx.jv_aftercool_product_source.factory_collection'])) {
            $connection->executeStatement(<<<'SQL'
                CREATE INDEX `idx.jv_aftercool_product_source.factory_collection`
                    ON `jv_aftercool_product_source` (`factory_id`, `stammartikel_id`)
                SQL);
        }
        if (!isset($indexes['idx.jv_aftercool_product_source.source_file_prefix'])) {
            $connection->executeStatement(<<<'SQL'
                CREATE INDEX `idx.jv_aftercool_product_source.source_file_prefix`
                    ON `jv_aftercool_product_source` (`source_file_prefix`)
                SQL);
        }
        if (!isset($indexes['idx.jv_aftercool_product_source.collection_name'])) {
            $connection->executeStatement(<<<'SQL'
                CREATE INDEX `idx.jv_aftercool_product_source.collection_name`
                    ON `jv_aftercool_product_source` (`collection_name`)
                SQL);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
