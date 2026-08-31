<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Dto;

final readonly class AfterCoolProductPage
{
    /** @param array<AfterCoolProductItem> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $limit,
        public int $offset,
        public bool $hasMore,
    ) {
    }
}
