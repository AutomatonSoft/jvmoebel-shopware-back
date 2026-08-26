<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Dto;

final readonly class ResolvedProductTax
{
    public function __construct(
        public string $id,
        public float $rate,
    ) {
    }
}
