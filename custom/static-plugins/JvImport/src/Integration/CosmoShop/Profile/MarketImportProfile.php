<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Profile;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;

final class MarketImportProfile
{
    public static function technicalName(Market $market): string
    {
        return 'jv_cosmoshop_product_'.str_replace('.', '_', $market->domain());
    }

    public static function marketForTechnicalName(mixed $technicalName): ?Market
    {
        if (!is_string($technicalName)) {
            return null;
        }

        foreach (Market::cases() as $market) {
            if (self::technicalName($market) === $technicalName) {
                return $market;
            }
        }

        return null;
    }

    /** @return list<array{key: string, mappedKey: string, position: int, requiredByUser?: bool, useDefaultValue?: bool, defaultValue?: string}> */
    public static function mapping(Market $market): array
    {
        $languageId = $market->languageId();

        return [
            ['key' => 'productNumber', 'mappedKey' => 'product_number', 'position' => 1, 'requiredByUser' => true],
            ['key' => 'active', 'mappedKey' => 'active', 'position' => 2],
            ['key' => 'stock', 'mappedKey' => 'stock', 'position' => 3],
            ['key' => 'ean', 'mappedKey' => 'ean', 'position' => 4, 'requiredByUser' => true],
            ['key' => 'weight', 'mappedKey' => 'weight', 'position' => 5],
            ['key' => 'length', 'mappedKey' => 'length', 'position' => 6],
            ['key' => 'width', 'mappedKey' => 'width', 'position' => 7],
            ['key' => 'height', 'mappedKey' => 'height', 'position' => 8],
            ['key' => 'minPurchase', 'mappedKey' => 'min_purchase', 'position' => 9, 'useDefaultValue' => true, 'defaultValue' => '1'],
            ['key' => 'maxPurchase', 'mappedKey' => 'max_purchase', 'position' => 10],
            ['key' => 'price.'.$market->currencyCode().'.gross', 'mappedKey' => 'price_gross', 'position' => 11, 'requiredByUser' => true],
            ['key' => 'price.'.$market->currencyCode().'.net', 'mappedKey' => 'price_net', 'position' => 12],
            ['key' => 'translations.'.$languageId.'.name', 'mappedKey' => 'name', 'position' => 13, 'requiredByUser' => true],
            ['key' => 'translations.'.$languageId.'.description', 'mappedKey' => 'description', 'position' => 14],
            ['key' => 'translations.'.$languageId.'.metaDescription', 'mappedKey' => 'short_description', 'position' => 15],
            ['key' => 'translations.'.$languageId.'.keywords', 'mappedKey' => 'keywords', 'position' => 16],
            ['key' => 'manufacturer.translations.DEFAULT.name', 'mappedKey' => 'manufacturer_name', 'position' => 17],
            ['key' => 'deliveryTimeId', 'mappedKey' => 'delivery_time_id', 'position' => 18],
            ['key' => 'unitId', 'mappedKey' => 'unit_id', 'position' => 19],
            ['key' => 'purchaseUnit', 'mappedKey' => 'contents', 'position' => 20],
            ['key' => 'referenceUnit', 'mappedKey' => 'reference_unit', 'position' => 21],
            ['key' => 'packUnit', 'mappedKey' => 'pack_unit', 'position' => 22],
        ];
    }
}
