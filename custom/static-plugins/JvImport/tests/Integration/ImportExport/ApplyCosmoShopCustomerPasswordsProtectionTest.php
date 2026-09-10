<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ApplyCosmoShopCustomerPasswordsProtectionTest extends AbstractCosmoShopImportExportTestCase
{
    private const string LEGACY_HASH = 's512##BRpXFjGlAzWDj9hP1sMBInj7pSr8AnC2POeJBU8tG36dXmTXJvPEyWYfKgzhByml5+Rkcilpk/rK3inzRw7T9A';

    private const string SALT = '0123456789abcdefghijklmnopqrst-_';

    public function testItDoesNotDowngradeCurrentCredentialsOnRepeatedSourceImport(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $sourceIds = [42990, 42991, 42992];
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-password-protection-');
        self::assertNotFalse($file);
        file_put_contents(
            $file,
            "source_customer_id;password_hash;salt\n"
            .'42990;'.self::LEGACY_HASH.';'.self::SALT."\n"
            ."42991;OldSourcePassword9;\n"
            ."42992;xx;xx\n",
        );

        $this->ensureMarketSalesChannel($market, $context);
        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');

        foreach ($sourceIds as $sourceId) {
            $this->createCustomer($sourceId, $market, $context);
            $payload = [
                'id' => CosmoShopCustomerIdentity::customerId($market, $sourceId),
                'password' => 'CurrentPassword9',
            ];
            if (42992 === $sourceId) {
                $payload = [...$payload,
                    'legacyPassword' => self::LEGACY_HASH.':'.self::SALT,
                    'legacyEncoder' => 'CosmoShopS512',
                ];
            }
            $customerRepository->update([$payload], $context);
        }

        try {
            $command = (new Application(static::getKernel()))->find('jv:cosmoshop:apply-customer-passwords');
            $tester = new CommandTester($command);
            self::assertSame(Command::SUCCESS, $tester->execute(['market' => $market->domain(), 'file' => $file]));
            self::assertStringContainsString('protected_current=3', $tester->getDisplay(true));

            foreach ($sourceIds as $sourceId) {
                $customer = $customerRepository->search(new Criteria([CosmoShopCustomerIdentity::customerId($market, $sourceId)]), $context)->first();
                self::assertInstanceOf(CustomerEntity::class, $customer);
                self::assertNotNull($customer->getPassword());
                self::assertTrue(password_verify('CurrentPassword9', $customer->getPassword()));
                self::assertNull($customer->getLegacyPassword());
                self::assertNull($customer->getLegacyEncoder());
            }
        } finally {
            foreach ($sourceIds as $sourceId) {
                $customerId = CosmoShopCustomerIdentity::customerId($market, $sourceId);
                if (null !== $customerRepository->searchIds(new Criteria([$customerId]), $context)->firstId()) {
                    $customerRepository->delete([['id' => $customerId]], $context);
                }
            }
            unlink($file);
        }
    }

    public function testMalformedSourceCredentialFailsWithoutOverwritingCurrentPassword(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $sourceId = 42989;
        $customerId = CosmoShopCustomerIdentity::customerId($market, $sourceId);
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-password-protection-');
        self::assertNotFalse($file);
        file_put_contents(
            $file,
            "source_customer_id;password_hash;salt\n{$sourceId};s512##malformed;".self::SALT."\n",
        );

        $this->ensureMarketSalesChannel($market, $context);
        $this->createCustomer($sourceId, $market, $context);
        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');
        $customerRepository->update([[
            'id' => $customerId,
            'boundSalesChannelId' => null,
            'password' => 'CurrentPassword9',
        ]], $context);

        try {
            $command = (new Application(static::getKernel()))->find('jv:cosmoshop:apply-customer-passwords');
            $tester = new CommandTester($command);

            self::assertSame(Command::FAILURE, $tester->execute(['market' => $market->domain(), 'file' => $file]));
            self::assertStringContainsString('protected_current=0', $tester->getDisplay(true));
            self::assertStringContainsString('failed=1', $tester->getDisplay(true));

            $customer = $customerRepository->search(new Criteria([$customerId]), $context)->first();
            self::assertInstanceOf(CustomerEntity::class, $customer);
            self::assertNotNull($customer->getPassword());
            self::assertTrue(password_verify('CurrentPassword9', $customer->getPassword()));
            self::assertSame($market->salesChannelId(), $customer->getBoundSalesChannelId());
        } finally {
            if (null !== $customerRepository->searchIds(new Criteria([$customerId]), $context)->firstId()) {
                $customerRepository->delete([['id' => $customerId]], $context);
            }
            unlink($file);
        }
    }

    private function createCustomer(int $sourceCustomerId, Market $market, Context $context): void
    {
        $customerId = CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId);
        $addressId = CosmoShopCustomerIdentity::billingAddressId($market, $sourceCustomerId);
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannel = $salesChannelRepository->search(new Criteria([$market->salesChannelId()]), $context)->first();
        self::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $countryId = $countryRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', 'DE')), $context)->firstId();
        self::assertNotNull($countryId);
        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');
        $customerRepository->create([[
            'id' => $customerId,
            'customerNumber' => $market->domain().'-'.$sourceCustomerId,
            'groupId' => $salesChannel->getCustomerGroupId(),
            'salesChannelId' => $market->salesChannelId(),
            'boundSalesChannelId' => $market->salesChannelId(),
            'languageId' => $market->languageId(),
            'firstName' => 'Password',
            'lastName' => 'Protection',
            'email' => 'password-protection-'.$sourceCustomerId.'@example.test',
            'password' => 'InitialPass9',
            'active' => true,
            'guest' => false,
            'accountType' => 'personal',
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [[
                'id' => $addressId,
                'firstName' => 'Password',
                'lastName' => 'Protection',
                'street' => 'Test street 1',
                'zipcode' => '10115',
                'city' => 'Berlin',
                'countryId' => $countryId,
            ]],
        ]], $context);
    }
}
