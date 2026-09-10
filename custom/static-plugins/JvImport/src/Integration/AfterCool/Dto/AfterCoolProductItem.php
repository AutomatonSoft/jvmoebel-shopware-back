<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Dto;

final readonly class AfterCoolProductItem
{
    /** @param array<string, mixed> $row */
    public function __construct(
        public string $account,
        public string $dataset,
        public int $factoryId,
        public string $productId,
        public string $ean,
        public string $artikelnummer,
        public string $name,
        public int $rowNo,
        public string $sourceFile,
        public string $sourceKind,
        public string $updatedAt,
        public array $row,
    ) {
    }
}
