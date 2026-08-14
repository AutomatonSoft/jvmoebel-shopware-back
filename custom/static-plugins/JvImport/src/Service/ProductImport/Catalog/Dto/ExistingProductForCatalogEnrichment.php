<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Dto;

final readonly class ExistingProductForCatalogEnrichment
{
    /** @param list<string> $propertyOptionIds
     * @param array<string, mixed> $customFields
     */
    public function __construct(
        public string $id,
        public string $productNumber,
        public ?string $ean,
        public string $currencyCode,
        public float $priceGross,
        public float $priceNet,
        public float $taxRate,
        public array $propertyOptionIds,
        public array $customFields,
    ) {
    }
}
