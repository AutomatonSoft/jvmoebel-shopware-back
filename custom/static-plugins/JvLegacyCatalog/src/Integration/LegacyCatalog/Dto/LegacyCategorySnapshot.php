<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Integration\LegacyCatalog\Dto;

final readonly class LegacyCategorySnapshot
{
    /**
     * @param list<LegacyCategoryRecord> $categories
     * @param array<string, mixed>       $diagnostics
     */
    public function __construct(
        public string $sourceSystem,
        public string $sourceProject,
        public int $formatVersion,
        public string $categoriesSha256,
        public int $categoryCount,
        public int $contentCount,
        public int $seoCount,
        public array $diagnostics,
        public array $categories,
    ) {
    }
}
