<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Customer;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerWishlistIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopCustomerWishlistIdentityTest extends TestCase
{
    public function testWishlistIdentityIncludesTheMarketAndCustomer(): void
    {
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.customer-wishlist.jvmoebel.de.42'),
            CosmoShopCustomerWishlistIdentity::wishlistId(Market::Germany, 42),
        );
        self::assertNotSame(
            CosmoShopCustomerWishlistIdentity::wishlistId(Market::Germany, 42),
            CosmoShopCustomerWishlistIdentity::wishlistId(Market::Austria, 42),
        );
    }

    public function testRelationIdentityIsStableForTheWishlistAndProduct(): void
    {
        $wishlistId = Uuid::randomHex();
        $productId = Uuid::randomHex();

        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.customer-wishlist-product.'.$wishlistId.'.'.$productId),
            CosmoShopCustomerWishlistIdentity::productRelationId($wishlistId, $productId),
        );
    }
}
