<?php declare(strict_types=1);

namespace Jv\Seo\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1790208000CreateRobotsPublicationRun extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1790208000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_seo_robots_publication_run` (
                `id` BINARY(16) NOT NULL,
                `initiator` VARCHAR(64) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `content` LONGTEXT NOT NULL,
                `status` VARCHAR(16) NOT NULL,
                `publication_plan` JSON NULL,
                `publication_result` JSON NULL,
                `safe_failure_code` VARCHAR(128) NULL,
                `safe_failure_message` VARCHAR(255) NULL,
                `started_at` DATETIME(3) NULL,
                `finished_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.jv_seo_robots_run.channel_created` (`sales_channel_id`, `created_at`),
                KEY `idx.jv_seo_robots_run.status_created` (`status`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
