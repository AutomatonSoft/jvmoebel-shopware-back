<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Dto;

final readonly class CatalogVariantCandidate
{
    public function __construct(
        public string $productId,
        public string $productNumber,
        public string $productReference,
        public float $priceGross,
        public string $sourceCode,
    ) {
    }
}
