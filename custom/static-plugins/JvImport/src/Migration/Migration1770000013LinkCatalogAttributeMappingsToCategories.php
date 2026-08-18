<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

final class Migration1770000013LinkCatalogAttributeMappingsToCategories extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000013;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->createSchemaManager()->listTableColumns('jv_catalog_category_attribute');
        if (!isset($columns['category_id'])) {
            $connection->executeStatement('ALTER TABLE `jv_catalog_category_attribute` ADD `category_id` BINARY(16) NULL AFTER `category_group_id`');
        }
        if (!isset($columns['category_version_id'])) {
            $connection->executeStatement('ALTER TABLE `jv_catalog_category_attribute` ADD `category_version_id` BINARY(16) NULL AFTER `category_id`');
            $connection->executeStatement(
                'UPDATE `jv_catalog_category_attribute` SET `category_version_id` = :liveVersion WHERE `category_version_id` IS NULL',
                ['liveVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            );
            $connection->executeStatement('ALTER TABLE `jv_catalog_category_attribute` MODIFY `category_version_id` BINARY(16) NOT NULL');
        }

        $indexes = $connection->createSchemaManager()->listTableIndexes('jv_catalog_category_attribute');
        if (!isset($indexes['idx.jv_catalog_category_attribute.category_version'])) {
            $connection->executeStatement('ALTER TABLE `jv_catalog_category_attribute` ADD KEY `idx.jv_catalog_category_attribute.category_version` (`category_id`, `category_version_id`)');
        }

        $foreignKeys = $connection->createSchemaManager()->listTableForeignKeys('jv_catalog_category_attribute');
        if (!$this->hasCategoryForeignKey($foreignKeys)) {
            $connection->executeStatement('ALTER TABLE `jv_catalog_category_attribute` ADD CONSTRAINT `fk.jv_catalog_category_attribute.category` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /** @param list<\Doctrine\DBAL\Schema\ForeignKeyConstraint> $foreignKeys */
    private function hasCategoryForeignKey(array $foreignKeys): bool
    {
        foreach ($foreignKeys as $foreignKey) {
            if ('fk.jv_catalog_category_attribute.category' === $foreignKey->getName()) {
                return true;
            }
        }

        return false;
    }
}
