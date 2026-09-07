<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopS512LegacyEncoder;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class CosmoShopLegacyPasswordLoginTest extends AbstractCosmoShopImportExportTestCase
{
    private const string TEST_PASSWORD = 'TestPass9';

    private const string TEST_SALT = '0123456789abcdefghijklmnopqrstuv';

    private const string TEST_SOURCE_HASH = 's512##5KYTzUCQXwRYpvOFJ3672U58CaBRkMtr4pyMh1mHTMNlegrAR503ZwK7m5A8TKFrGMQpQSjuHj9hMFDl6lAg8g';

    public function testSuccessfulLegacyLoginRehashesThePasswordAndClearsLegacyFields(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $customerId = CosmoShopCustomerIdentity::customerId($market, 42999);
        $addressId = CosmoShopCustomerIdentity::billingAddressId($market, 42999);
        $email = 'legacy-customer-42999@example.test';

        $this->ensureMarketSalesChannel($market, $context);

        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannel = $salesChannelRepository->search(new Criteria([$market->salesChannelId()]), $context)->first();
        self::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $countryId = $countryRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('iso', 'DE')),
            $context,
        )->firstId();
        self::assertNotNull($countryId);

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');

        try {
            $customerRepository->create([[
                'id' => $customerId,
                'customerNumber' => 'jvmoebel.de-42999',
                'groupId' => $salesChannel->getCustomerGroupId(),
                'salesChannelId' => $market->salesChannelId(),
                'boundSalesChannelId' => $market->salesChannelId(),
                'languageId' => $market->languageId(),
                'firstName' => 'Legacy',
                'lastName' => 'Customer',
                'email' => $email,
                'active' => true,
                'guest' => false,
                'accountType' => 'personal',
                'legacyPassword' => self::TEST_SOURCE_HASH.':'.self::TEST_SALT,
                'legacyEncoder' => (new CosmoShopS512LegacyEncoder())->getName(),
                'defaultBillingAddressId' => $addressId,
                'defaultShippingAddressId' => $addressId,
                'addresses' => [[
                    'id' => $addressId,
                    'firstName' => 'Legacy',
                    'lastName' => 'Customer',
                    'street' => 'Test street 1',
                    'zipcode' => '10115',
                    'city' => 'Berlin',
                    'countryId' => $countryId,
                ]],
            ]], $context);

            $contextFactory = static::getContainer()->get(CachedSalesChannelContextFactory::class);
            self::assertInstanceOf(AbstractSalesChannelContextFactory::class, $contextFactory);
            $salesChannelContext = $contextFactory->create(Uuid::randomHex(), $market->salesChannelId());

            $accountService = static::getContainer()->get(AccountService::class);
            self::assertInstanceOf(AccountService::class, $accountService);
            $accountService->loginByCredentials($email, self::TEST_PASSWORD, $salesChannelContext);

            $customer = $customerRepository->search(new Criteria([$customerId]), $context)->first();
            self::assertInstanceOf(CustomerEntity::class, $customer);
            self::assertNull($customer->getLegacyPassword());
            self::assertNull($customer->getLegacyEncoder());
            self::assertNotNull($customer->getPassword());
            self::assertTrue(password_verify(self::TEST_PASSWORD, $customer->getPassword()));
        } finally {
            if (null !== $customerRepository->searchIds(new Criteria([$customerId]), $context)->firstId()) {
                $customerRepository->delete([['id' => $customerId]], $context);
            }
        }
    }
}
