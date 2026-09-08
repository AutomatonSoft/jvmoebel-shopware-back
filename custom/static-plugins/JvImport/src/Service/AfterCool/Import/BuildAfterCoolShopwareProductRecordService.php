<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Import;

use Jv\Import\Service\AfterCool\Dto\AfterCoolMappedProduct;
use Jv\Import\Service\AfterCool\Exception\AfterCoolProductWriteValidationException;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\Import\Service\ProductImport\ProductManufacturerIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;

final class BuildAfterCoolShopwareProductRecordService
{
    /**
     * @param list<array<string, mixed>> $existingPrices
     *
     * @return array<string, mixed>
     */
    public function build(AfterCoolMappedProduct $product, ?string $existingProductId, string $taxId, float $taxRate, string $currencyId, string $languageId, array $existingPrices = []): array
    {
        $isNew = null === $existingProductId;
        if ($isNew && (null === $product->grossPrice || 0.0 >= $product->grossPrice)) {
            throw new AfterCoolProductWriteValidationException('invalid_price');
        }
        $record = [
            'id' => $existingProductId ?? ProductImportIdentity::fromProductNumber($product->productNumber),
            'productNumber' => $product->productNumber,
            'ean' => $product->ean,
            'stock' => $product->stock,
            'translations' => [[
                'languageId' => $languageId,
                'name' => $product->name,
            ]],
        ];
        if (null !== $product->description) {
            $record['translations'][0]['description'] = $product->description;
        }
        if (null !== $product->grossPrice && 0.0 < $product->grossPrice) {
            $prices = [];
            foreach ($existingPrices as $price) {
                if (isset($price['currencyId']) && is_string($price['currencyId'])) {
                    $prices[$price['currencyId']] = $price;
                }
            }
            $prices[$currencyId] = [
                'currencyId' => $currencyId,
                'gross' => $product->grossPrice,
                'net' => round($product->grossPrice / (1 + $taxRate / 100), 2),
                'linked' => true,
                'listPrice' => [
                    'gross' => $this->uvp($product->grossPrice),
                    'net' => round($this->uvp($product->grossPrice) / (1 + $taxRate / 100), 2),
                    'linked' => true,
                ],
            ];
            $record['price'] = array_values($prices);
        }
        if ($isNew) {
            $record['name'] = $product->name;
            $record['taxId'] = $taxId;
            $record['minPurchase'] = 1;
            $record['active'] = false;
        }
        $record['manufacturer'] = ['id' => ProductManufacturerIdentity::jvmoebel(), 'name' => 'JVMOEBEL'];
        $record['visibilities'] = [[
            'id' => Uuid::fromStringToHex('jvmoebel.product-visibility.'.Market::Germany->salesChannelId().$record['id']),
            'salesChannelId' => Market::Germany->salesChannelId(),
            'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
        ]];

        return $record;
    }

    private function uvp(float $price): float
    {
        $factor = $price > 5000 ? 1.10 : ($price >= 2500 && $price <= 4999 ? 1.18 : ($price >= 1000 && $price <= 2499 ? 1.25 : 1.35));

        return round($price * $factor, 2);
    }
}
