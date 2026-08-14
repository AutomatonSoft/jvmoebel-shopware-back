<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb\Dto;

final readonly class OkbProductVariation
{
    /** @param list<OkbProductAttribute> $attributes */
    public function __construct(
        public string $productReference,
        public string $sku,
        public string $ean,
        public string $categoryName,
        public ?float $standardPriceAmount,
        public ?string $currency,
        public array $attributes,
    ) {
    }
}
