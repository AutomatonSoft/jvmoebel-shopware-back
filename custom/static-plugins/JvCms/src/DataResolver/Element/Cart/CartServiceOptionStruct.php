<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Shopware\Core\Framework\Struct\Struct;

final class CartServiceOptionStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected string $label,
        protected float $price,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_service_option';
    }
}
