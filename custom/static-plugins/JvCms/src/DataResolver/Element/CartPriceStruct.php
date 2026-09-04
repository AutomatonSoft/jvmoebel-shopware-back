<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `lineItems[].price` for `jv-cart`. */
final class CartPriceStruct extends Struct
{
    public function __construct(
        protected float $unitPrice,
        protected ?float $uvp,
        protected ?int $discountPercent,
    ) {
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getUvp(): ?float
    {
        return $this->uvp;
    }

    public function getDiscountPercent(): ?int
    {
        return $this->discountPercent;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_price';
    }
}
