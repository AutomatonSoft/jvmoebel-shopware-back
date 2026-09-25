<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Integration\LegacyCatalog\Dto;

final readonly class LegacyCategoryRecord
{
    /**
     * @param array<string, mixed>       $rubric
     * @param list<array<string, mixed>> $content
     * @param list<array<string, mixed>> $seo
     */
    public function __construct(
        public int $sourceCategoryId,
        public int $sourceParentId,
        public array $rubric,
        public array $content,
        public array $seo,
    ) {
    }
}
