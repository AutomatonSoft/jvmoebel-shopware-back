<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000015RemoveCatalogProductAttributesField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000015;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM `custom_field` WHERE `name` = :name', ['name' => 'jv_catalog_attributes']);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
