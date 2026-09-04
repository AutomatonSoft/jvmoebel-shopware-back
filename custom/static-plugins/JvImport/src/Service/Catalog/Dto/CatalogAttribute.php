<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog\Dto;

final readonly class CatalogAttribute
{
    public function __construct(
        public string $sourceKey,
        public string $categoryGroupSourceKey,
        public string $name,
        public string $type,
        public ?string $sourceRelevance,
        public bool $multiValue,
    ) {
    }
}
