<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog\Dto;

final readonly class CatalogSchemaSnapshot
{
    /**
     * @param list<CatalogCategoryGroup>                  $categoryGroups
     * @param list<CatalogCategory>                       $categories
     * @param list<CatalogAttribute>                      $attributes
     * @param list<CatalogAllowedValue>                   $allowedValues
     * @param list<CatalogNavigationCategory>             $navigationCategories
     * @param list<CatalogCategoryGroupNavigationMapping> $categoryGroupNavigationMappings
     */
    public function __construct(
        public string $sourceCode,
        public array $categoryGroups,
        public array $categories,
        public array $attributes,
        public array $allowedValues,
        public array $navigationCategories = [],
        public array $categoryGroupNavigationMappings = [],
    ) {
    }
}
