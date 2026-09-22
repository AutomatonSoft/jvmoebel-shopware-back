<?php declare(strict_types=1);

namespace Jv\Seo\Service\CategoryMapping\Dto;

final readonly class CategoryMappingExportResult
{
    public function __construct(
        public int $processedLegacyCategories,
        public int $candidateRows,
        public int $legacyCategoriesWithoutMatches,
        public int $newCategoriesWithoutMatches,
        public string $mappingFile,
        public string $legacyCategoriesWithoutMatchesFile,
        public string $newCategoriesWithoutMatchesFile,
    ) {
    }
}
