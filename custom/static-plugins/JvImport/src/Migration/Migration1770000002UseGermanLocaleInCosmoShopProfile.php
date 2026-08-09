<?php declare(strict_types=1);

namespace Jv\CatalogImport\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1770000002UseGermanLocaleInCosmoShopProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000002;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            "UPDATE import_export_profile SET mapping = REPLACE(mapping, 'translations.DEFAULT.', 'translations.de-DE.') WHERE technical_name = :name",
            ['name' => 'jv_cosmoshop_product_jvmoebel_de'],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
