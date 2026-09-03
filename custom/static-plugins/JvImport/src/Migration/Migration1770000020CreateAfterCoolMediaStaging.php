<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000020CreateAfterCoolMediaStaging extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000020;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_aftercool_media_stage` (
                `id` BINARY(16) NOT NULL,
                `run_id` BINARY(16) NOT NULL,
                `offset` INT NOT NULL,
                `source_product_id` VARCHAR(64) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `url` VARCHAR(2048) NOT NULL,
                `position` INT NOT NULL,
                `cover_candidate` TINYINT(1) NOT NULL,
                `status` VARCHAR(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.jv_aftercool_media_stage.task` (`run_id`, `offset`, `product_id`, `url`),
                KEY `idx.jv_aftercool_media_stage.pending` (`run_id`, `offset`, `status`),
                CONSTRAINT `fk.jv_aftercool_media_stage.run`
                    FOREIGN KEY (`run_id`) REFERENCES `jv_aftercool_import_run` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
