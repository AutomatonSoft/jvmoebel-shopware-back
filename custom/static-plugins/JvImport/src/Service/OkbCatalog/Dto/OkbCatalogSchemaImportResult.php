<?php declare(strict_types=1);

namespace Jv\Import\Service\OkbCatalog\Dto;

final readonly class OkbCatalogSchemaImportResult
{
    public function __construct(
        public int $categoryGroups,
        public int $categories,
        public int $attributeRelations,
        public int $propertyGroups,
        public int $propertyOptions,
    ) {
    }
}
