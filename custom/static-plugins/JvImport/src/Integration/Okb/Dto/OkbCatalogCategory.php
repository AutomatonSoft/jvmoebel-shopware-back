<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb\Dto;

final readonly class OkbCatalogCategory
{
    public function __construct(
        public string $categoryGroupId,
        public string $categoryGroup,
        public string $categoryId,
        public string $name,
    ) {
    }
}
