<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb\Profile;

use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Framework\Uuid\Uuid;

final class CatalogProductImportProfile
{
    public const TECHNICAL_NAME = 'jv_catalog_prepared_product';

    /** @return array{id: string, technicalName: string, type: string, sourceEntity: string, fileType: string, delimiter: string, enclosure: string, mapping: list<array{key: string, mappedKey: string, position: int}>, updateBy: list<string>, config: array<never, never>} */
    public static function definition(): array
    {
        return [
            'id' => Uuid::fromStringToHex('jvmoebel.import-profile.'.self::TECHNICAL_NAME),
            'technicalName' => self::TECHNICAL_NAME,
            'type' => ImportExportProfileEntity::TYPE_IMPORT,
            'sourceEntity' => 'product',
            'fileType' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'mapping' => [
                ['key' => 'productNumber', 'mappedKey' => 'product_number', 'position' => 1],
            ],
            'updateBy' => ['id'],
            'config' => [],
        ];
    }
}
