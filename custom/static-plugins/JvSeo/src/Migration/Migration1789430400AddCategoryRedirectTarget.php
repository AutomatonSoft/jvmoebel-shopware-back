<?php declare(strict_types=1);

namespace Jv\Seo\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1789430400AddCategoryRedirectTarget extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1789430400;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            ALTER TABLE `jv_seo_redirect`
                ADD COLUMN `category_id` BINARY(16) NULL AFTER `product_version_id`,
                ADD COLUMN `category_version_id` BINARY(16) NULL AFTER `category_id`,
                ADD UNIQUE KEY `uniq.jv_seo_redirect.category` (`type`, `category_id`, `category_version_id`),
                ADD CONSTRAINT `fk.jv_seo_redirect.category`
                    FOREIGN KEY (`category_id`, `category_version_id`)
                    REFERENCES `category` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
