<?php declare(strict_types=1);

namespace Jv\Seo\Service\CategoryMapping\Dto;

final readonly class CategoryMappingScoreInput
{
    /**
     * @param list<string>                          $legacyPath
     * @param list<string>                          $newPath
     * @param list<array{path: string, count: int}> $googleTaxonomies
     */
    public function __construct(
        public int $matchedProducts,
        public int $legacyEligibleProducts,
        public int $newCategoryProducts,
        public string $legacyName,
        public string $legacyMetaTitle,
        public string $legacyMetaDescription,
        public string $legacyUrlKey,
        public array $legacyPath,
        public string $newName,
        public string $newMetaTitle,
        public string $newMetaDescription,
        public string $newSeoPathInfo,
        public array $newPath,
        public array $googleTaxonomies,
    ) {
    }
}
