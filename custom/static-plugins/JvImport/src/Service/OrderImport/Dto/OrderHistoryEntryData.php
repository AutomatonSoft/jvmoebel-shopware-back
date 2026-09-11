<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

final readonly class OrderHistoryEntryData
{
    public function __construct(
        public string $occurredAt,
        public OrderHistoryStatus $status,
    ) {
    }
}
