<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Dto;

final readonly class AfterCoolInvalidProductItem
{
    public function __construct(
        public ?string $productId,
        public ?string $artikelnummer,
        public ?string $ean,
        public ?int $rowNo,
        public string $code = 'invalid_product_item',
    ) {
    }
}
