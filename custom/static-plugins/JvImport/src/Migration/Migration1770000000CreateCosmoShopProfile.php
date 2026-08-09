<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

final class Migration1770000000CreateCosmoShopProfile extends MigrationStep
{
    private const string PROFILE = 'jv_cosmoshop_product_jvmoebel_de';

    public function getCreationTimestamp(): int
    {
        return 1770000000;
    }

    public function update(Connection $connection): void
    {
        $id = Uuid::fromStringToHex('jvmoebel.import-profile.'.self::PROFILE);
        $mapping = [
            ['key' => 'id', 'mappedKey' => 'product_id', 'position' => 0],
            ['key' => 'productNumber', 'mappedKey' => 'product_number', 'position' => 1],
            ['key' => 'active', 'mappedKey' => 'active', 'position' => 2],
            ['key' => 'stock', 'mappedKey' => 'stock', 'position' => 3],
            ['key' => 'ean', 'mappedKey' => 'ean', 'position' => 4],
            ['key' => 'taxId', 'mappedKey' => 'tax_id', 'position' => 5],
            ['key' => 'weight', 'mappedKey' => 'weight', 'position' => 6],
            ['key' => 'minPurchase', 'mappedKey' => 'min_purchase', 'position' => 7],
            ['key' => 'maxPurchase', 'mappedKey' => 'max_purchase', 'position' => 8],
            ['key' => 'price.DEFAULT.gross', 'mappedKey' => 'price_gross', 'position' => 9],
            ['key' => 'price.DEFAULT.net', 'mappedKey' => 'price_net', 'position' => 10],
            ['key' => 'translations.de-DE.name', 'mappedKey' => 'name', 'position' => 11],
            ['key' => 'translations.de-DE.description', 'mappedKey' => 'description', 'position' => 12],
            ['key' => 'translations.de-DE.metaDescription', 'mappedKey' => 'short_description', 'position' => 13],
            ['key' => 'translations.de-DE.keywords', 'mappedKey' => 'keywords', 'position' => 14],
        ];

        $connection->executeStatement(
            'INSERT INTO import_export_profile (id, technical_name, type, source_entity, file_type, delimiter, enclosure, mapping, update_by, config, created_at) VALUES (:id, :name, :type, :entity, :fileType, :delimiter, :enclosure, :mapping, :updateBy, :config, NOW(3)) ON DUPLICATE KEY UPDATE mapping = VALUES(mapping), update_by = VALUES(update_by), config = VALUES(config)',
            ['id' => Uuid::fromHexToBytes($id), 'name' => self::PROFILE, 'type' => 'import', 'entity' => 'product', 'fileType' => 'text/csv', 'delimiter' => ';', 'enclosure' => '"', 'mapping' => json_encode($mapping, \JSON_THROW_ON_ERROR), 'updateBy' => json_encode(['id'], \JSON_THROW_ON_ERROR), 'config' => json_encode([], \JSON_THROW_ON_ERROR)],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
