<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000019AddAfterCoolSourceLookupIndexes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000019;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE INDEX `idx.jv_aftercool_product_source.factory_artikelnummer`
                ON `jv_aftercool_product_source` (`account`, `dataset`, `factory_id`, `source_artikelnummer`)
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE INDEX `idx.jv_aftercool_product_source.factory_ean`
                ON `jv_aftercool_product_source` (`account`, `dataset`, `factory_id`, `source_ean`)
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
