<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

/** Immutable normalized boundary object for one source order aggregate. */
final readonly class CosmoShopOrderData
{
    /** @param array<string, mixed> $value */
    public function __construct(private array $value)
    {
    }

    public function sourceOrderId(): int
    {
        return $this->value['source_order_id'];
    }

    public function orderNumber(): string
    {
        return $this->value['order_number'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->value;
    }
}
