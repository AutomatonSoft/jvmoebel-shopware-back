<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\LookupData\Dto;

final readonly class ProductImportLookupItemData
{
    /** @param array<string, string> $labels */
    public function __construct(
        public string $sourceId,
        public array $labels,
    ) {
    }
}
