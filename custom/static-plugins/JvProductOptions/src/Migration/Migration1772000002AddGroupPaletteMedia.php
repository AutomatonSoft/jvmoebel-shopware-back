<?php declare(strict_types=1);

namespace Jv\ProductOptions\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1772000002AddGroupPaletteMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772000002;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `jv_option_template_group`
                ADD COLUMN `palette_media_id` BINARY(16) NULL AFTER `default_value_id`,
                ADD CONSTRAINT `fk.jv_option_template_group.palette_media_id`
                    FOREIGN KEY (`palette_media_id`) REFERENCES `media` (`id`)
                    ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
