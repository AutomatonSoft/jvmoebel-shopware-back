<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopCustomerWishlistIdentity
{
    public static function wishlistId(Market $market, int $sourceCustomerId): string
    {
        return Uuid::fromStringToHex('jvmoebel.customer-wishlist.'.$market->domain().'.'.$sourceCustomerId);
    }

    public static function productRelationId(string $wishlistId, string $productId): string
    {
        return Uuid::fromStringToHex('jvmoebel.customer-wishlist-product.'.$wishlistId.'.'.$productId);
    }
}
