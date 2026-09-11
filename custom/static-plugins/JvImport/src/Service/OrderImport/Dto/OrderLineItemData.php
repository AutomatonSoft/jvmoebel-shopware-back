<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

final readonly class OrderLineItemData
{
    /** @param array<string, mixed> $snapshot Opaque validated snapshot, consumed only by payload projection. */
    public function __construct(
        public int $sourcePositionId,
        public int $position,
        public OrderLineItemKind $kind,
        public string $mainProductNumber,
        public string $productNumber,
        public string $label,
        public string $description,
        public int $quantity,
        public string $taxRate,
        public string $unitNet,
        public string $unitTax,
        public string $totalNet,
        public string $totalTax,
        public array $snapshot,
    ) {
    }
}
