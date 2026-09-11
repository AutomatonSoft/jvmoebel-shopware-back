<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

final readonly class OrderPaymentData
{
    public function __construct(
        public OrderPaymentKey $key,
        public string $label,
        public string $sourcePlugin,
        public ?string $transactionReference,
    ) {
    }
}
