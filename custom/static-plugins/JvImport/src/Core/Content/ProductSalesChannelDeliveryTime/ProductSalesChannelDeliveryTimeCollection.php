<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\ProductSalesChannelDeliveryTime;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<ProductSalesChannelDeliveryTimeEntity> */
final class ProductSalesChannelDeliveryTimeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductSalesChannelDeliveryTimeEntity::class;
    }
}
