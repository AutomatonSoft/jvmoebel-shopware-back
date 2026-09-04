<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Shopware\Core\Framework\Struct\Struct;

final class CartDeliveryStruct extends Struct
{
    public function __construct(
        protected string $estimate,
        protected string $method,
    ) {
    }

    public function getEstimate(): string
    {
        return $this->estimate;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_delivery';
    }
}
