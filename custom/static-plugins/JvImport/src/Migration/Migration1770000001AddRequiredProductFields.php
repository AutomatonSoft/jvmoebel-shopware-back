<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000001AddRequiredProductFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            "UPDATE import_export_profile SET mapping = JSON_ARRAY_APPEND(mapping, '$', JSON_OBJECT('key', 'stock', 'mappedKey', 'stock', 'position', 3)) WHERE technical_name = :name AND JSON_SEARCH(mapping, 'one', 'stock', NULL, '$[*].key') IS NULL",
            ['name' => 'jv_cosmoshop_product_jvmoebel_de'],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
