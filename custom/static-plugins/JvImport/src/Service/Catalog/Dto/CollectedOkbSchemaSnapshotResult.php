<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog\Dto;

final readonly class CollectedOkbSchemaSnapshotResult
{
    public function __construct(
        public int $categoryGroups,
        public int $categories,
        public int $attributes,
        public int $allowedValues,
        public int $attributeFailures,
        public string $outputDirectory,
    ) {
    }
}
