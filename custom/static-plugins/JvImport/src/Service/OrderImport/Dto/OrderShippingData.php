<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

final readonly class OrderShippingData
{
    public function __construct(
        public OrderShippingKey $key,
        public string $label,
        public ?int $sourceCarrierId,
    ) {
    }
}
