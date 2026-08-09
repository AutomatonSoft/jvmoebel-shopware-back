<?php declare(strict_types=1);

namespace Jv\CatalogImport\Service\ProductImport\LookupData\Dto;

final readonly class ProductImportLookupData
{
    /**
     * @param list<ProductImportLookupItemData> $deliveryTimes
     * @param list<ProductImportLookupItemData> $units
     */
    public function __construct(
        public array $deliveryTimes,
        public array $units,
    ) {
    }
}
