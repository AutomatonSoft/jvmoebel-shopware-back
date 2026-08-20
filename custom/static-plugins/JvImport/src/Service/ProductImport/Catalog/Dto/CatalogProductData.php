<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Dto;

final readonly class CatalogProductData
{
    /** @param list<CatalogProductAttribute> $attributes */
    public function __construct(
        public string $sourceCode,
        public string $productNumber,
        public string $ean,
        public string $categoryId,
        public string $categoryGroupId,
        public ?float $standardPriceAmount,
        public ?string $currency,
        public array $attributes,
    ) {
    }
}
