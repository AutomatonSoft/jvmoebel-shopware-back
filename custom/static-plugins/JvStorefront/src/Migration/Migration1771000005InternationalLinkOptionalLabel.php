<?php declare(strict_types=1);

namespace Jv\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1771000005InternationalLinkOptionalLabel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1771000005;
    }

    public function update(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['jv_storefront_international_link'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('jv_storefront_international_link');
        if (!isset($columns['label'])) {
            $connection->executeStatement(
                'ALTER TABLE `jv_storefront_international_link` ADD `label` VARCHAR(255) NULL AFTER `target_sales_channel_id`'
            );

            return;
        }

        if (!$columns['label']->getNotnull()) {
            return;
        }

        $connection->executeStatement(<<<'SQL'
            ALTER TABLE `jv_storefront_international_link`
                MODIFY `label` VARCHAR(255) NULL
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
