<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog\Dto;

final readonly class CatalogAttributeMapping
{
    public function __construct(
        public string $sourceCode,
        public string $categoryGroupId,
        public string $attributeId,
        public string $attributeName,
        public string $attributeType,
        public ?string $featureRelevance,
        public bool $multiValue,
        public bool $active,
        public bool $enabled,
        public string $storage,
        public ?string $propertyGroupId,
        public ?string $customFieldName,
    ) {
    }
}
