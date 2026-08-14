<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Dto;

final readonly class CatalogCategoryAttributeSchema
{
    public function __construct(
        public string $sourceCode,
        public string $categoryGroupId,
        public string $attributeId,
        public string $attributeName,
        public string $attributeType,
        public bool $multiValue,
        public string $storage,
        public ?string $propertyGroupId,
    ) {
    }
}
