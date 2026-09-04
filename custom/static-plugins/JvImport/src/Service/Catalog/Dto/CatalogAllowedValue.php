<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog\Dto;

final readonly class CatalogAllowedValue
{
    public function __construct(
        public string $attributeSourceKey,
        public int $position,
        public string $value,
    ) {
    }
}
