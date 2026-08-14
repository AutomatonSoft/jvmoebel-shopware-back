<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Dto;

final readonly class CatalogVariantPlan
{
    /**
     * @param list<CatalogVariantParent> $parents
     * @param array<string, string>      $childParentIds
     */
    public function __construct(
        public array $parents,
        public array $childParentIds,
    ) {
    }
}
