<?php declare(strict_types=1);

namespace Jv\CatalogImport\Service\ProductImport\Dto;

final readonly class ResolvedProductTax
{
    public function __construct(
        public string $id,
        public float $rate,
    ) {
    }
}
