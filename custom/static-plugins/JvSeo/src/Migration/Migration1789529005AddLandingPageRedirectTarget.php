<?php declare(strict_types=1);

namespace Jv\Seo\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1789529005AddLandingPageRedirectTarget extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1789529005;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            ALTER TABLE `jv_seo_redirect`
                ADD COLUMN `landing_page_id` BINARY(16) NULL AFTER `category_version_id`,
                ADD COLUMN `landing_page_version_id` BINARY(16) NULL AFTER `landing_page_id`,
                ADD UNIQUE KEY `uniq.jv_seo_redirect.landing_page` (`type`, `landing_page_id`, `landing_page_version_id`),
                ADD CONSTRAINT `fk.jv_seo_redirect.landing_page`
                    FOREIGN KEY (`landing_page_id`, `landing_page_version_id`)
                    REFERENCES `landing_page` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
