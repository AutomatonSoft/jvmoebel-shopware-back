<?php declare(strict_types=1);

namespace Jv\Seo\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1789610100AddSitemapPublicationPlan extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1789610100;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->createSchemaManager()->listTableColumns('jv_seo_sitemap_export_run');
        if (isset($columns['publication_plan'])) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `jv_seo_sitemap_export_run` ADD COLUMN `publication_plan` JSON NULL AFTER `scope`');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
