<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopCustomerIdentity
{
    public static function customerId(Market $market, int $sourceCustomerId): string
    {
        return self::id('customer', $market, (string) $sourceCustomerId);
    }

    public static function billingAddressId(Market $market, int $sourceCustomerId): string
    {
        return self::id('customer-address.'.$market->domain().'.billing', $market, (string) $sourceCustomerId, false);
    }

    public static function shippingAddressId(Market $market, int|string $sourceAddressId): string
    {
        return self::id('customer-address.'.$market->domain().'.shipping', $market, (string) $sourceAddressId, false);
    }

    public static function fallbackShippingAddressId(Market $market, int $sourceCustomerId): string
    {
        return self::shippingAddressId($market, 'customer-'.$sourceCustomerId);
    }

    public static function newsletterRecipientId(Market $market, string $email): string
    {
        return self::id('newsletter-recipient', $market, mb_strtolower(trim($email)));
    }

    private static function id(string $type, Market $market, string $sourceId, bool $includeMarket = true): string
    {
        $prefix = $includeMarket
            ? 'jvmoebel.'.$type.'.'.$market->domain().'.'
            : 'jvmoebel.'.$type.'.';

        return Uuid::fromStringToHex($prefix.$sourceId);
    }
}
