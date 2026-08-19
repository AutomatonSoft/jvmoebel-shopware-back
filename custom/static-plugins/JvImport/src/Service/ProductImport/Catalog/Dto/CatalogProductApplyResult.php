<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Dto;

final readonly class CatalogProductApplyResult
{
    /** @param list<CatalogProductInvalidRecord> $invalidRecords */
    public function __construct(
        public int $products,
        public int $propertyOptions,
        public int $variantParents,
        public array $invalidRecords = [],
    ) {
    }
}
