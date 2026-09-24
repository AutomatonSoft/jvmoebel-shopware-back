<?php declare(strict_types=1);

namespace Jv\Seo\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1790208001AddImportLockToRedirect extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1790208001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            ALTER TABLE jv_seo_redirect
                ADD COLUMN import_locked TINYINT(1) NOT NULL DEFAULT 0
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
