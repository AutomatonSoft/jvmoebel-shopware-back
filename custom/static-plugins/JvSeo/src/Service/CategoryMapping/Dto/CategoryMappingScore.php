<?php declare(strict_types=1);

namespace Jv\Seo\Service\CategoryMapping\Dto;

final readonly class CategoryMappingScore
{
    public function __construct(
        public int $score,
        public int $legacyCoverage,
        public int $targetPurity,
        public int $semanticSimilarity,
        public int $hierarchyConsistency,
        public int $googleTaxonomySimilarity,
    ) {
    }
}
