<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000005RequireCosmoShopEan extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000005;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
                UPDATE import_export_profile
                SET mapping = JSON_SET(
                    mapping,
                    CONCAT(JSON_UNQUOTE(JSON_SEARCH(mapping, 'one', 'ean', NULL, '$[*].mappedKey')), '.requiredByUser'),
                    TRUE
                )
                WHERE technical_name LIKE 'jv_cosmoshop_product_%'
                  AND JSON_SEARCH(mapping, 'one', 'ean', NULL, '$[*].mappedKey') IS NOT NULL
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
