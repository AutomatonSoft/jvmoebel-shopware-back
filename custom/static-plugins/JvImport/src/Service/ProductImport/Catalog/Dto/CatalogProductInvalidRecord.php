<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Dto;

final readonly class CatalogProductInvalidRecord
{
    public function __construct(
        public string $sourceCode,
        public string $productNumber,
        public string $ean,
        public string $reason,
    ) {
    }
}
