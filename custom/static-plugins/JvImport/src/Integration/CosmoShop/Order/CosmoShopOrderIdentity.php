<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopOrderIdentity
{
    public static function orderId(Market $market, int $sourceOrderId): string
    {
        return self::id('order', $market, (string) $sourceOrderId);
    }

    public static function orderCustomerId(Market $market, int $sourceOrderId): string
    {
        return self::id('order-customer', $market, (string) $sourceOrderId);
    }

    public static function billingAddressId(Market $market, int $sourceOrderId): string
    {
        return self::id('order-address', $market, $sourceOrderId.'.billing');
    }

    public static function shippingAddressId(Market $market, int $sourceOrderId): string
    {
        return self::id('order-address', $market, $sourceOrderId.'.shipping');
    }

    public static function lineItemId(Market $market, int $sourcePositionId): string
    {
        return self::id('order-line-item', $market, (string) $sourcePositionId);
    }

    public static function transactionId(Market $market, int $sourceOrderId): string
    {
        return self::id('order-transaction', $market, (string) $sourceOrderId);
    }

    public static function deliveryId(Market $market, int $sourceOrderId): string
    {
        return self::id('order-delivery', $market, (string) $sourceOrderId);
    }

    public static function paymentMethodId(Market $market, string $key): string
    {
        return self::id('order-payment-method', $market, $key);
    }

    public static function shippingMethodId(Market $market, string $key): string
    {
        return self::id('order-shipping-method', $market, $key);
    }

    private static function id(string $type, Market $market, string $source): string
    {
        return Uuid::fromStringToHex('jvmoebel.'.$type.'.'.$market->domain().'.'.$source);
    }
}
