<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Service\LegacyCatalog\Dto;

final readonly class LegacyCategoryImportResult
{
    /** @param list<string> $salesChannelDomains */
    public function __construct(
        public string $status,
        public string $sourceProject,
        public string $salesChannelId,
        public string $salesChannelName,
        public array $salesChannelDomains,
        public string $categoriesSha256,
        public int $categoryCount,
        public int $rootCount,
        public int $contentCount,
        public int $seoCount,
        public int $categoriesWithoutContent,
        public int $categoriesWithoutSeo,
        public int $orphanSeoCount,
        /** @var array<string, int> */
        public array $diagnosticCounts,
    ) {
    }
}
