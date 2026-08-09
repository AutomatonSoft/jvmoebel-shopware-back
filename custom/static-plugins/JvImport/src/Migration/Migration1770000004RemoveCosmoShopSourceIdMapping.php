<?php declare(strict_types=1);

namespace Jv\CatalogImport\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000004RemoveCosmoShopSourceIdMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000004;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            "UPDATE import_export_profile SET mapping = JSON_REMOVE(mapping, REPLACE(JSON_UNQUOTE(JSON_SEARCH(mapping, 'one', 'id', NULL, '$[*].key')), '.key', '')) WHERE technical_name LIKE 'jv_cosmoshop_product_%' AND JSON_SEARCH(mapping, 'one', 'id', NULL, '$[*].key') IS NOT NULL",
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
