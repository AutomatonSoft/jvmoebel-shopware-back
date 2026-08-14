<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Dto;

final readonly class CatalogProductUpdate
{
    /** @param list<string> $categoryIds
     * @param list<string>         $propertyOptionIds
     * @param array<string, mixed> $customFields
     */
    public function __construct(
        public string $productId,
        public array $categoryIds,
        public array $propertyOptionIds,
        public array $customFields,
        public float $priceGross,
        public float $priceNet,
    ) {
    }
}
