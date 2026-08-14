<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000011AddCatalogAttributeMappingState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000011;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->createSchemaManager()->listTableColumns('jv_catalog_category_attribute');
        if (!isset($columns['active'])) {
            $connection->executeStatement('ALTER TABLE `jv_catalog_category_attribute` ADD `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `multi_value`');
        }
        if (!isset($columns['enabled'])) {
            $connection->executeStatement('ALTER TABLE `jv_catalog_category_attribute` ADD `enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `active`');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
