<?php declare(strict_types=1);

namespace Jv\Import\Service\OkbCatalog\Dto;

final readonly class OkbProductMappingPreparationResult
{
    public function __construct(
        public int $products,
        public int $attributes,
        public int $failures,
    ) {
    }
}
