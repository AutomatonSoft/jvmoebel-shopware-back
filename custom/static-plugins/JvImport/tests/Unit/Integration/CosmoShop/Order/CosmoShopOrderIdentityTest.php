<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Order;

use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopOrderIdentityTest extends TestCase
{
    public function testAggregateIdentitiesAreDeterministicAndMarketScoped(): void
    {
        $market = Market::Germany;

        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.order.jvmoebel.de.6401'),
            CosmoShopOrderIdentity::orderId($market, 6401),
        );
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.order-customer.jvmoebel.de.6401'),
            CosmoShopOrderIdentity::orderCustomerId($market, 6401),
        );
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.order-address.jvmoebel.de.6401.billing'),
            CosmoShopOrderIdentity::billingAddressId($market, 6401),
        );
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.order-address.jvmoebel.de.6401.shipping'),
            CosmoShopOrderIdentity::shippingAddressId($market, 6401),
        );
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.order-line-item.jvmoebel.de.9201'),
            CosmoShopOrderIdentity::lineItemId($market, 9201),
        );
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.order-transaction.jvmoebel.de.6401'),
            CosmoShopOrderIdentity::transactionId($market, 6401),
        );
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.order-delivery.jvmoebel.de.6401'),
            CosmoShopOrderIdentity::deliveryId($market, 6401),
        );

        self::assertNotSame(
            CosmoShopOrderIdentity::orderId(Market::Germany, 6401),
            CosmoShopOrderIdentity::orderId(Market::Austria, 6401),
        );
        self::assertNotSame(
            CosmoShopOrderIdentity::paymentMethodId(Market::Germany, 'paypal'),
            CosmoShopOrderIdentity::paymentMethodId(Market::Austria, 'paypal'),
        );
        self::assertNotSame(
            CosmoShopOrderIdentity::shippingMethodId(Market::Germany, 'freight_forwarder'),
            CosmoShopOrderIdentity::shippingMethodId(Market::Austria, 'freight_forwarder'),
        );
    }
}
