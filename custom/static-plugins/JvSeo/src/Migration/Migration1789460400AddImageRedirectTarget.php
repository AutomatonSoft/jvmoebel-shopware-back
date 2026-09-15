<?php declare(strict_types=1);

namespace Jv\Seo\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1789460400AddImageRedirectTarget extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1789460400;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            ALTER TABLE `jv_seo_redirect`
                ADD COLUMN `media_id` BINARY(16) NULL AFTER `category_version_id`,
                ADD UNIQUE KEY `uniq.jv_seo_redirect.media` (`type`, `media_id`),
                ADD CONSTRAINT `fk.jv_seo_redirect.media`
                    FOREIGN KEY (`media_id`)
                    REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
