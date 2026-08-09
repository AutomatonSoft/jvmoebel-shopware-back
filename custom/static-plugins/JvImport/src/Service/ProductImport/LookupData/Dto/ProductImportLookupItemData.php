<?php declare(strict_types=1);

namespace Jv\CatalogImport\Service\ProductImport\LookupData\Dto;

final readonly class ProductImportLookupItemData
{
    /** @param array<string, string> $labels */
    public function __construct(
        public string $sourceId,
        public array $labels,
    ) {
    }
}
