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
        $tableExists = (bool) $connection->fetchOne(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'jv_storefront_international_link'
             LIMIT 1"
        );

        if (!$tableExists) {
            return;
        }

        $isNullable = $connection->fetchOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'jv_storefront_international_link'
               AND COLUMN_NAME = 'label'
             LIMIT 1"
        );

        if ('YES' === $isNullable) {
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
