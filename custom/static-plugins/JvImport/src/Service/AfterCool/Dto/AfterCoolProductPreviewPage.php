<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Dto;

final readonly class AfterCoolProductPreviewPage
{
    /** @param list<AfterCoolProductPreviewItem> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $limit,
        public int $offset,
        public bool $hasMore,
    ) {
    }
}
