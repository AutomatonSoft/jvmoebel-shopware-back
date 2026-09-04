<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog\Dto;

final readonly class CatalogNavigationCategory
{
    public function __construct(
        public string $sourceKey,
        public ?string $parentSourceKey,
        public string $name,
    ) {
    }
}
