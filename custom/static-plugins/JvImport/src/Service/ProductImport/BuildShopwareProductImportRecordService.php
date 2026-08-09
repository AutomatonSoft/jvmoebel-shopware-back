<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport;

use Jv\Import\Integration\CosmoShop\CosmoShopManufacturerIdentity;
use Jv\Import\Integration\CosmoShop\CosmoShopProductIdentity;
use Jv\Import\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\Import\Service\ProductImport\Dto\CosmoShopProductImportData;
use Jv\Import\Service\ProductImport\Dto\ResolvedProductTax;
use Jv\Import\Service\ProductImport\Exception\InvalidCosmoShopProductImportDataException;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

final class BuildShopwareProductImportRecordService
{
    /**
     * @return array<string, mixed>
     */
    public function execute(
        CosmoShopProductImportData $data,
        Market $market,
        string $languageId,
        ResolvedProductTax $tax,
    ): array {
        $record = $data->mappedRecord;
        $id = CosmoShopProductIdentity::fromProductNumber($data->productNumber);
        $record['id'] = $id;
        $record['active'] = 0 === (int) $data->sourceInactive;
        $record['stock'] = (int) $data->stock;
        $record['minPurchase'] = max(1, (int) ($data->minPurchase ?? '1'));
        $record['purchaseUnit'] = max(1.0, (float) ($data->contents ?? '1'));
        $record['referenceUnit'] = max(1.0, (float) ($data->referenceUnit ?? '1'));
        $record['packUnit'] = $data->packUnit ?? 'Stück';
        $record['taxId'] = $tax->id;

        if (null !== $data->manufacturerName) {
            $record['manufacturer'] ??= [];
            $record['manufacturer']['id'] = CosmoShopManufacturerIdentity::fromName($data->manufacturerName);
        }
        if (null !== $data->deliveryTimeId) {
            $record['deliveryTimeId'] = CosmoShopReferenceIdentity::deliveryTimeId($data->deliveryTimeId);
        }
        if (null !== $data->unitId && 0 < (int) $data->unitId) {
            $record['unitId'] = CosmoShopReferenceIdentity::unitId($data->unitId);
        }

        $record['price'] = $this->prices($record['price'] ?? [], $data, $tax);
        $record['translations'] = $this->translations($record['translations'] ?? []);
        $record['visibilities'] = [[
            'id' => Uuid::fromStringToHex('jvmoebel.product-visibility.'.$market->salesChannelId().$id),
            'salesChannelId' => $market->salesChannelId(),
            'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
        ]];

        if (null !== $data->seoPath) {
            $record['seoUrls'] = [[
                'id' => Uuid::fromStringToHex('jvmoebel.product-seo-url.'.$market->domain().$id),
                'salesChannelId' => $market->salesChannelId(),
                'languageId' => $languageId,
                'routeName' => 'frontend.detail.page',
                'pathInfo' => 'detail/'.$id,
                'seoPathInfo' => trim($data->seoPath, '/'),
                'isCanonical' => true,
                'isModified' => true,
            ]];
        }

        return $record;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function prices(mixed $prices, CosmoShopProductImportData $data, ResolvedProductTax $tax): array
    {
        if (!is_array($prices) || [] === $prices) {
            $prices = [['currencyId' => Defaults::CURRENCY, 'gross' => (float) $data->priceGross]];
        }

        foreach ($prices as $index => $price) {
            if (!is_array($price)) {
                throw new InvalidCosmoShopProductImportDataException('CosmoShop price mapping is invalid.');
            }

            $gross = (float) ($price['gross'] ?? $data->priceGross);
            if ($gross <= 0) {
                throw new InvalidCosmoShopProductImportDataException('CosmoShop mapped price must be positive.');
            }

            $normalized = [
                'currencyId' => $price['currencyId'] ?? Defaults::CURRENCY,
                'net' => $this->net($gross, $tax->rate),
                'gross' => $gross,
                'linked' => false,
            ];
            $listPriceGross = (float) ($data->listPriceGross ?? (is_array($price['listPrice'] ?? null) ? ($price['listPrice']['gross'] ?? 0) : 0));
            if ($listPriceGross > $gross) {
                $normalized['listPrice'] = [
                    'gross' => $listPriceGross,
                    'net' => $this->net($listPriceGross, $tax->rate),
                    'linked' => false,
                ];
            }
            $prices[$index] = $normalized;
        }

        /* @var list<array<string, mixed>> $prices */
        return $prices;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function translations(mixed $translations): array
    {
        if (!is_array($translations)) {
            return [];
        }

        $systemTranslation = null;
        foreach ($translations as $languageId => $translation) {
            if (!is_array($translation)) {
                continue;
            }
            $translation['metaDescription'] = mb_substr(trim(strip_tags((string) ($translation['metaDescription'] ?? ''))), 0, 255);
            $translations[$languageId] = $translation;
            $systemTranslation ??= $translation;
        }
        if (is_array($systemTranslation) && !isset($translations[Defaults::LANGUAGE_SYSTEM])) {
            $translations[Defaults::LANGUAGE_SYSTEM] = $systemTranslation;
        }

        /* @var array<string, array<string, mixed>> $translations */
        return $translations;
    }

    private function net(float $gross, float $taxRate): float
    {
        return round($gross / (1 + ($taxRate / 100)), 2);
    }
}
