<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

final class Migration1770000000CreateCosmoShopProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000000;
    }

    public function update(Connection $connection): void
    {
        foreach (MarketImportProfile::definitions() as $profile) {
            $connection->executeStatement(
                'INSERT INTO import_export_profile (id, technical_name, type, source_entity, file_type, delimiter, enclosure, mapping, update_by, config, created_at) VALUES (:id, :name, :type, :entity, :fileType, :delimiter, :enclosure, :mapping, :updateBy, :config, NOW(3)) ON DUPLICATE KEY UPDATE mapping = VALUES(mapping), update_by = VALUES(update_by), config = VALUES(config)',
                [
                    'id' => Uuid::fromHexToBytes($profile['id']),
                    'name' => $profile['technicalName'],
                    'type' => $profile['type'],
                    'entity' => $profile['sourceEntity'],
                    'fileType' => $profile['fileType'],
                    'delimiter' => $profile['delimiter'],
                    'enclosure' => $profile['enclosure'],
                    'mapping' => json_encode($profile['mapping'], \JSON_THROW_ON_ERROR),
                    'updateBy' => json_encode($profile['updateBy'], \JSON_THROW_ON_ERROR),
                    'config' => json_encode($profile['config'], \JSON_THROW_ON_ERROR),
                ],
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
