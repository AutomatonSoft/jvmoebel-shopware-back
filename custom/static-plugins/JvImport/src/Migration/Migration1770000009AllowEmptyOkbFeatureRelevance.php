<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000009AllowEmptyOkbFeatureRelevance extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000009;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `jv_import_okb_category_group_attribute` MODIFY `feature_relevance` VARCHAR(255) NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
