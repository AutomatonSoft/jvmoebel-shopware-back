<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000007UpdateCosmoShopProfileMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000007;
    }

    public function update(Connection $connection): void
    {
        foreach (MarketImportProfile::definitions() as $profile) {
            $connection->executeStatement(
                'UPDATE import_export_profile SET mapping = :mapping, update_by = :updateBy, config = :config WHERE technical_name = :technicalName',
                [
                    'technicalName' => $profile['technicalName'],
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
