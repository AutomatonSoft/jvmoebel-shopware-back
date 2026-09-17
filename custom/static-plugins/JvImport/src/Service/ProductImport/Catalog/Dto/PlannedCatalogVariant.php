<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Dto;

use Jv\Import\Integration\Okb\Dto\OkbProductVariation;

final readonly class PlannedCatalogVariant
{
    /** @param array<string, string> $axisValues */
    public function __construct(
        public OkbProductVariation $variation,
        public int $position,
        public array $axisValues,
    ) {
    }
}
