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
     * @param list<array<string, mixed>>          $existingPrices
     * @param array<string, array<string, mixed>> $existingTranslations
     *
     * @return array<string, mixed>
     */
    public function execute(
        CosmoShopProductImportData $data,
        Market $market,
        string $languageId,
        ResolvedProductTax $tax,
        ?string $existingProductId,
        array $existingPrices,
        array $existingTranslations,
        string $marketCurrencyId,
    ): array {
        $record = $data->mappedRecord;
        $id = $existingProductId ?? CosmoShopProductIdentity::fromProductNumber($data->productNumber);
        $record['id'] = $id;
        $record['active'] = 0 === (int) $data->sourceInactive;
        $record['ean'] = $data->ean;
        $record['stock'] = (int) $data->stock;
        $record['minPurchase'] = (int) $data->minPurchase;
        $record['purchaseUnit'] = (float) $data->contents;
        $record['referenceUnit'] = (float) $data->referenceUnit;
        $record['packUnit'] = $data->packUnit ?? 'Stück';
        $record['taxId'] = $tax->id;

        if (null === $data->maxPurchase || $this->isZero($data->maxPurchase)) {
            unset($record['maxPurchase']);
        } else {
            $record['maxPurchase'] = (int) $data->maxPurchase;
        }

        if (null !== $data->manufacturerName) {
            $record['manufacturer'] ??= [];
            $record['manufacturer']['id'] = CosmoShopManufacturerIdentity::fromName($data->manufacturerName);
        }
        if (null !== $data->deliveryTimeId) {
            $record['deliveryTimeId'] = CosmoShopReferenceIdentity::deliveryTimeId($market, $data->deliveryTimeId);
        }
        if (null !== $data->unitId && 0 < (int) $data->unitId) {
            $record['unitId'] = CosmoShopReferenceIdentity::unitId($market, $data->unitId);
        }

        $record['price'] = $this->prices($record['price'] ?? [], $data, $tax, $existingPrices, $marketCurrencyId);
        $record['translations'] = $this->translations($record['translations'] ?? [], $market, $existingTranslations);
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
    /** @param list<array<string, mixed>> $existingPrices
     * @return list<array<string, mixed>>
     */
    private function prices(mixed $prices, CosmoShopProductImportData $data, ResolvedProductTax $tax, array $existingPrices, string $marketCurrencyId): array
    {
        $gross = (float) $data->priceGross;
        if ($gross <= 0) {
            throw new InvalidCosmoShopProductImportDataException('CosmoShop mapped price must be positive.');
        }
        $normalizedPrices = [$marketCurrencyId => [
            'currencyId' => $marketCurrencyId,
            'net' => $this->net($gross, $tax->rate),
            'gross' => $gross,
            'linked' => false,
        ]];
        $listPriceGross = (float) ($data->listPriceGross ?? 0);
        if ($listPriceGross > $gross) {
            $normalizedPrices[$marketCurrencyId]['listPrice'] = [
                'gross' => $listPriceGross,
                'net' => $this->net($listPriceGross, $tax->rate),
                'linked' => false,
            ];
        }

        foreach ($existingPrices as $existingPrice) {
            if (!is_string($existingPrice['currencyId'] ?? null)) {
                continue;
            }
            $normalizedPrices[$existingPrice['currencyId']] ??= $existingPrice;
        }
        if (!isset($normalizedPrices[Defaults::CURRENCY])) {
            $normalizedPrices[Defaults::CURRENCY] = $normalizedPrices[$marketCurrencyId];
            $normalizedPrices[Defaults::CURRENCY]['currencyId'] = Defaults::CURRENCY;
        }

        return array_values($normalizedPrices);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    /** @param array<string, array<string, mixed>> $existingTranslations
     * @return array<string, array<string, mixed>>
     */
    private function translations(mixed $translations, Market $market, array $existingTranslations): array
    {
        if (!is_array($translations)) {
            return $existingTranslations;
        }

        foreach ($translations as $languageId => $translation) {
            if (!is_array($translation)) {
                continue;
            }
            $translation['metaDescription'] = mb_substr(trim(strip_tags((string) ($translation['metaDescription'] ?? ''))), 0, 255);
            $translations[$languageId] = $translation;
        }
        $translations = array_replace($existingTranslations, $translations);
        if ((Market::Germany === $market || !isset($translations[Defaults::LANGUAGE_SYSTEM])) && isset($translations[$market->languageId()])) {
            $translations[Defaults::LANGUAGE_SYSTEM] = $translations[$market->languageId()];
        }

        /* @var array<string, array<string, mixed>> $translations */
        return $translations;
    }

    private function net(float $gross, float $taxRate): float
    {
        return round($gross / (1 + ($taxRate / 100)), 2);
    }

    private function isZero(string $value): bool
    {
        return (bool) preg_match('/^0+(?:\.0+)?$/', $value);
    }
}
