<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Dto;

final readonly class CatalogVariantParent
{
    public function __construct(
        public string $id,
        public string $productNumber,
        public float $priceGross,
    ) {
    }
}
