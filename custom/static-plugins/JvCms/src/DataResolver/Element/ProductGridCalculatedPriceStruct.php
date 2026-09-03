<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Storefront price block matching frontend `calculatedPrice` shape. */
final class ProductGridCalculatedPriceStruct extends Struct
{
    public function __construct(
        protected float $unitPrice,
        protected ?ProductGridListPriceStruct $listPrice,
    ) {
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getListPrice(): ?ProductGridListPriceStruct
    {
        return $this->listPrice;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_product_grid_calculated_price';
    }
}
