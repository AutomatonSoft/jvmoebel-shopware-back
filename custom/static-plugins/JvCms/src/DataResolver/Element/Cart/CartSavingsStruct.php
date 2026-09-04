<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Shopware\Core\Framework\Struct\Struct;

final class CartSavingsStruct extends Struct
{
    public function __construct(
        protected string $label,
        protected float $amount,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_savings';
    }
}
