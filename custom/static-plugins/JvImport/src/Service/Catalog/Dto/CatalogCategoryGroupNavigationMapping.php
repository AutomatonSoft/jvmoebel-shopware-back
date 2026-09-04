<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog\Dto;

final readonly class CatalogCategoryGroupNavigationMapping
{
    public function __construct(
        public string $categoryGroupSourceKey,
        public string $navigationSourceKey,
    ) {
    }
}
