<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\ProductSalesChannelDeliveryTime;

final class ProductSalesChannelDeliveryTimeCacheTag
{
    public static function forLink(string $linkId): string
    {
        return 'jv-import-product-sales-channel-delivery-time-'.$linkId;
    }
}
