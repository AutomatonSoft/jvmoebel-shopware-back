<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000016TrackCatalogProductRelations extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000016;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `jv_catalog_product_relation` (`product_id` BINARY(16) NOT NULL, `product_version_id` BINARY(16) NOT NULL, `relation_type` VARCHAR(32) NOT NULL, `relation_id` BINARY(16) NOT NULL, PRIMARY KEY (`product_id`, `product_version_id`, `relation_type`, `relation_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
