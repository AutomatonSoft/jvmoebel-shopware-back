<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Struck list price nested under `calculatedPrice.listPrice`. */
final class ProductGridListPriceStruct extends Struct
{
    public function __construct(
        protected float $price,
    ) {
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_product_grid_list_price';
    }
}
