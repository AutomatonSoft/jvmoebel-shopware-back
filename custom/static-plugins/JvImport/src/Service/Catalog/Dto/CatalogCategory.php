<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog\Dto;

final readonly class CatalogCategory
{
    public function __construct(
        public string $sourceKey,
        public string $categoryGroupSourceKey,
        public string $name,
    ) {
    }
}
