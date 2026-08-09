<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Dto;

/**
 * Raw CosmoShop values normalized from one CSV row before business validation.
 *
 * @phpstan-type MappedProductRecord array<string, mixed>
 */
final readonly class CosmoShopProductImportData
{
    /** @param MappedProductRecord $mappedRecord */
    public function __construct(
        public string $productNumber,
        public string $sourceInactive,
        public string $stock,
        public string $priceGross,
        public ?string $minPurchase,
        public ?string $contents,
        public ?string $referenceUnit,
        public ?string $packUnit,
        public ?string $manufacturerName,
        public ?string $deliveryTimeId,
        public ?string $unitId,
        public ?string $listPriceGross,
        public ?string $description,
        public ?string $seoPath,
        public array $mappedRecord,
    ) {
    }
}
