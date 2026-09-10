<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Customer;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopCustomerIdentityTest extends TestCase
{
    public function testItUsesStableMarketScopedCustomerAndAddressIds(): void
    {
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.customer.jvmoebel.de.42'),
            CosmoShopCustomerIdentity::customerId(Market::Germany, 42),
        );
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.customer-address.jvmoebel.de.billing.42'),
            CosmoShopCustomerIdentity::billingAddressId(Market::Germany, 42),
        );
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.customer-address.jvmoebel.de.shipping.17'),
            CosmoShopCustomerIdentity::shippingAddressId(Market::Germany, 17),
        );
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.customer-address.jvmoebel.de.shipping.customer-42'),
            CosmoShopCustomerIdentity::fallbackShippingAddressId(Market::Germany, 42),
        );
    }

    public function testItNeverMergesTheSameSourceCustomerAcrossMarkets(): void
    {
        self::assertNotSame(
            CosmoShopCustomerIdentity::customerId(Market::Germany, 42),
            CosmoShopCustomerIdentity::customerId(Market::Austria, 42),
        );
        self::assertNotSame(
            CosmoShopCustomerIdentity::billingAddressId(Market::Germany, 42),
            CosmoShopCustomerIdentity::billingAddressId(Market::Austria, 42),
        );
    }

    public function testItNormalizesNewsletterEmailButKeepsItMarketScoped(): void
    {
        $germanyId = CosmoShopCustomerIdentity::newsletterRecipientId(Market::Germany, ' Customer@Example.COM ');

        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.newsletter-recipient.jvmoebel.de.customer@example.com'),
            $germanyId,
        );
        self::assertSame(
            $germanyId,
            CosmoShopCustomerIdentity::newsletterRecipientId(Market::Germany, 'customer@example.com'),
        );
        self::assertNotSame(
            $germanyId,
            CosmoShopCustomerIdentity::newsletterRecipientId(Market::Austria, 'customer@example.com'),
        );
    }
}
