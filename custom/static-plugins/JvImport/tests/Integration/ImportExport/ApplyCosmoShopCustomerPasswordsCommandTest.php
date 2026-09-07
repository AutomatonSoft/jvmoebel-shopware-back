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

final class ApplyCosmoShopCustomerPasswordsCommandTest extends AbstractCosmoShopImportExportTestCase
{
    private const string TEST_SALT = '0123456789abcdefghijklmnopqrstuv';

    private const string TEST_SOURCE_HASH = 's512##5KYTzUCQXwRYpvOFJ3672U58CaBRkMtr4pyMh1mHTMNlegrAR503ZwK7m5A8TKFrGMQpQSjuHj9hMFDl6lAg8g';

    public function testDryRunAndRepeatedApplyAreSafeAndNeverPrintPasswordMaterial(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $customerId = CosmoShopCustomerIdentity::customerId($market, 42998);
        $path = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-customer-passwords-');
        self::assertNotFalse($path);
        file_put_contents(
            $path,
            "source_customer_id;password_hash;salt\n42998;".self::TEST_SOURCE_HASH.';'.self::TEST_SALT."\n",
        );

        $this->ensureMarketSalesChannel($market, $context);
        $this->createCustomer(42998, $market, $context);

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');

        try {
            $command = (new Application(static::getKernel()))->find('jv:cosmoshop:apply-customer-passwords');
            $tester = new CommandTester($command);

            self::assertSame(Command::SUCCESS, $tester->execute([
                'market' => $market->domain(),
                'file' => $path,
                '--dry-run' => true,
            ]));
            $customer = $customerRepository->search(new Criteria([$customerId]), $context)->first();
            self::assertInstanceOf(CustomerEntity::class, $customer);
            self::assertNull($customer->getLegacyPassword());
            self::assertNull($customer->getLegacyEncoder());

            self::assertSame(Command::SUCCESS, $tester->execute([
                'market' => $market->domain(),
                'file' => $path,
            ]));
            self::assertSame(Command::SUCCESS, $tester->execute([
                'market' => $market->domain(),
                'file' => $path,
            ]));

            $output = $tester->getDisplay(true);
            self::assertStringNotContainsString(self::TEST_SOURCE_HASH, $output);
            self::assertStringNotContainsString(self::TEST_SALT, $output);
            self::assertStringNotContainsString('TestPass9', $output);

            $customer = $customerRepository->search(new Criteria([$customerId]), $context)->first();
            self::assertInstanceOf(CustomerEntity::class, $customer);
            self::assertSame(self::TEST_SOURCE_HASH.':'.self::TEST_SALT, $customer->getLegacyPassword());
            self::assertSame('CosmoShopS512', $customer->getLegacyEncoder());
        } finally {
            if (null !== $customerRepository->searchIds(new Criteria([$customerId]), $context)->firstId()) {
                $customerRepository->delete([['id' => $customerId]], $context);
            }
            unlink($path);
        }
    }

    public function testSourceEmptyPasswordSentinelRequiresResetAndIsNeverApplied(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $sourceCustomerId = 42997;
        $customerId = CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId);
        $path = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-customer-passwords-');
        self::assertNotFalse($path);
        file_put_contents(
            $path,
            "source_customer_id;password_hash;salt\n{$sourceCustomerId};xx;xx\n",
        );

        $this->ensureMarketSalesChannel($market, $context);
        $this->createCustomer($sourceCustomerId, $market, $context);

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');

        try {
            $customerBefore = $customerRepository->search(new Criteria([$customerId]), $context)->first();
            self::assertInstanceOf(CustomerEntity::class, $customerBefore);
            $passwordBefore = $customerBefore->getPassword();
            self::assertNotNull($passwordBefore);

            $command = (new Application(static::getKernel()))->find('jv:cosmoshop:apply-customer-passwords');
            $tester = new CommandTester($command);

            self::assertSame(Command::SUCCESS, $tester->execute([
                'market' => $market->domain(),
                'file' => $path,
            ]));

            $output = $tester->getDisplay(true);
            self::assertStringContainsString('processed=1', $output);
            self::assertStringContainsString('reset_required=1', $output);
            self::assertStringContainsString('failed=0', $output);
            self::assertStringNotContainsString((string) $sourceCustomerId, $output);

            $customerAfter = $customerRepository->search(new Criteria([$customerId]), $context)->first();
            self::assertInstanceOf(CustomerEntity::class, $customerAfter);
            self::assertSame($passwordBefore, $customerAfter->getPassword());
            self::assertNull($customerAfter->getLegacyPassword());
            self::assertNull($customerAfter->getLegacyEncoder());
        } finally {
            if (null !== $customerRepository->searchIds(new Criteria([$customerId]), $context)->firstId()) {
                $customerRepository->delete([['id' => $customerId]], $context);
            }
            unlink($path);
        }
    }

    public function testSourcePlaintextPasswordIsImmediatelyHashedAndNeverPrinted(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $sourceCustomerId = 42996;
        $sourcePassword = 'SyntheticSourcePass9';
        $customerId = CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId);
        $path = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-customer-passwords-');
        self::assertNotFalse($path);
        file_put_contents(
            $path,
            "source_customer_id;password_hash;salt\n{$sourceCustomerId};{$sourcePassword};\n",
        );

        $this->ensureMarketSalesChannel($market, $context);
        $this->createCustomer($sourceCustomerId, $market, $context);

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');

        try {
            $command = (new Application(static::getKernel()))->find('jv:cosmoshop:apply-customer-passwords');
            $tester = new CommandTester($command);

            self::assertSame(Command::SUCCESS, $tester->execute([
                'market' => $market->domain(),
                'file' => $path,
            ]));

            $output = $tester->getDisplay(true);
            self::assertStringContainsString('processed=1', $output);
            self::assertStringContainsString('rehash=1', $output);
            self::assertStringContainsString('failed=0', $output);
            self::assertStringNotContainsString($sourcePassword, $output);
            self::assertStringNotContainsString((string) $sourceCustomerId, $output);

            $customer = $customerRepository->search(new Criteria([$customerId]), $context)->first();
            self::assertInstanceOf(CustomerEntity::class, $customer);
            self::assertNotNull($customer->getPassword());
            self::assertNotSame($sourcePassword, $customer->getPassword());
            self::assertTrue(password_verify($sourcePassword, $customer->getPassword()));
            self::assertNull($customer->getLegacyPassword());
            self::assertNull($customer->getLegacyEncoder());
        } finally {
            if (null !== $customerRepository->searchIds(new Criteria([$customerId]), $context)->firstId()) {
                $customerRepository->delete([['id' => $customerId]], $context);
            }
            unlink($path);
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
        $countryId = $countryRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('iso', 'DE')),
            $context,
        )->firstId();
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
            'lastName' => 'Import',
            'email' => 'password-import-'.$sourceCustomerId.'@example.test',
            'password' => 'InitialPass9',
            'active' => true,
            'guest' => false,
            'accountType' => 'personal',
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [[
                'id' => $addressId,
                'firstName' => 'Password',
                'lastName' => 'Import',
                'street' => 'Test street 1',
                'zipcode' => '10115',
                'city' => 'Berlin',
                'countryId' => $countryId,
            ]],
        ]], $context);
    }
}
