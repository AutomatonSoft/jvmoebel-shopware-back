<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
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

final class ApplyCosmoShopCustomerAddressesCommandTest extends AbstractCosmoShopImportExportTestCase
{
    public function testDryRunApplyAndRepeatPreserveTheDefaultShippingAddressWithoutDuplicates(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $sourceCustomerId = 42101;
        $customerId = CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId);
        $defaultShippingId = CosmoShopCustomerIdentity::shippingAddressId($market, 92101);
        $additionalId = CosmoShopCustomerIdentity::shippingAddressId($market, 92102);
        $file = $this->addressFile([
            [$sourceCustomerId, 92101, 'mrs', '', 'Ada', 'Lovelace', '', 'Updated street 1', '10115', 'Berlin', 'DE', '+49 30 1'],
            [$sourceCustomerId, 92102, 'mr', '', 'Alan', 'Turing', '', 'Second street 2', '1010', 'Wien', 'AT', '+43 1 2'],
        ]);

        $this->ensureMarketSalesChannel($market, $context);
        $this->createCustomer($market, $sourceCustomerId, 92101, $context);

        /** @var EntityRepository<CustomerAddressCollection> $addressRepository */
        $addressRepository = static::getContainer()->get('customer_address.repository');
        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');

        try {
            $command = (new Application(static::getKernel()))->find('jv:cosmoshop:apply-customer-addresses');
            $tester = new CommandTester($command);

            self::assertSame(Command::SUCCESS, $tester->execute([
                'market' => $market->domain(),
                'file' => $file,
                '--dry-run' => true,
            ]));
            self::assertSame(2, $this->addressCount($addressRepository, $customerId, $context));

            self::assertSame(Command::SUCCESS, $tester->execute(['market' => $market->domain(), 'file' => $file]));
            self::assertSame(Command::SUCCESS, $tester->execute(['market' => $market->domain(), 'file' => $file]));

            self::assertSame(3, $this->addressCount($addressRepository, $customerId, $context));
            $defaultShipping = $addressRepository->search(new Criteria([$defaultShippingId]), $context)->first();
            self::assertInstanceOf(CustomerAddressEntity::class, $defaultShipping);
            self::assertSame('Updated street 1', $defaultShipping->getStreet());
            self::assertInstanceOf(CustomerAddressEntity::class, $addressRepository->search(new Criteria([$additionalId]), $context)->first());

            $customer = $customerRepository->search(new Criteria([$customerId]), $context)->first();
            self::assertInstanceOf(CustomerEntity::class, $customer);
            self::assertSame($defaultShippingId, $customer->getDefaultShippingAddressId());

            $output = $tester->getDisplay(true);
            self::assertStringContainsString('processed=2', $output);
            self::assertStringContainsString('missing_customer=0', $output);
            self::assertStringContainsString('failed=0', $output);
            self::assertStringNotContainsString((string) $sourceCustomerId, $output);
            self::assertStringNotContainsString('Ada', $output);
            self::assertStringNotContainsString('Updated street', $output);
        } finally {
            $this->deleteCustomer($customerRepository, $customerId, $context);
            unlink($file);
        }
    }

    public function testInvalidMissingAndDuplicateRowsFailButDoNotBlockAValidAddress(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $sourceCustomerId = 42102;
        $customerId = CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId);
        $validAddressId = CosmoShopCustomerIdentity::shippingAddressId($market, 92202);
        $file = $this->addressFile([
            [$sourceCustomerId, 92202, 'mr', '', 'Valid', 'Recipient', '', 'Valid street 1', '10115', 'Berlin', 'DE', ''],
            [999999, 999001, 'mr', '', 'Missing', 'Customer', '', 'Missing street 1', '10115', 'Berlin', 'DE', ''],
            [$sourceCustomerId, 92203, 'mr', '', 'Duplicate', 'One', '', 'Duplicate street 1', '10115', 'Berlin', 'DE', ''],
            [$sourceCustomerId, 92203, 'mr', '', 'Duplicate', 'Two', '', 'Duplicate street 2', '10115', 'Berlin', 'DE', ''],
            [$sourceCustomerId, 92204, 'mr', '', 'Unknown', 'Country', '', 'Unknown street 1', '10115', 'Berlin', 'ZZ', ''],
        ]);

        $this->ensureMarketSalesChannel($market, $context);
        $this->createCustomer($market, $sourceCustomerId, 92201, $context);

        /** @var EntityRepository<CustomerAddressCollection> $addressRepository */
        $addressRepository = static::getContainer()->get('customer_address.repository');
        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');

        try {
            $tester = new CommandTester((new Application(static::getKernel()))->find('jv:cosmoshop:apply-customer-addresses'));
            self::assertSame(Command::FAILURE, $tester->execute(['market' => $market->domain(), 'file' => $file]));

            self::assertInstanceOf(CustomerAddressEntity::class, $addressRepository->search(new Criteria([$validAddressId]), $context)->first());
            self::assertNull($addressRepository->search(new Criteria([
                CosmoShopCustomerIdentity::shippingAddressId($market, 92203),
            ]), $context)->first());

            $output = $tester->getDisplay(true);
            self::assertStringContainsString('processed=5', $output);
            self::assertStringContainsString('missing_customer=1', $output);
            self::assertStringContainsString('failed=3', $output);
            foreach (['42102', '999999', '92203', 'Valid', 'Duplicate', 'Unknown street'] as $sensitiveValue) {
                self::assertStringNotContainsString($sensitiveValue, $output);
            }
        } finally {
            $this->deleteCustomer($customerRepository, $customerId, $context);
            unlink($file);
        }
    }

    private function createCustomer(Market $market, int $sourceCustomerId, int $sourceShippingAddressId, Context $context): void
    {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannel = $salesChannelRepository->search(new Criteria([$market->salesChannelId()]), $context)->first();
        self::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $countryId = $countryRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', 'DE')), $context)->firstId();
        self::assertNotNull($countryId);

        $customerId = CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId);
        $billingId = CosmoShopCustomerIdentity::billingAddressId($market, $sourceCustomerId);
        $shippingId = CosmoShopCustomerIdentity::shippingAddressId($market, $sourceShippingAddressId);

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');
        $customerRepository->create([[
            'id' => $customerId,
            'customerNumber' => $market->domain().'-'.$sourceCustomerId,
            'groupId' => $salesChannel->getCustomerGroupId(),
            'salesChannelId' => $market->salesChannelId(),
            'boundSalesChannelId' => $market->salesChannelId(),
            'languageId' => $market->languageId(),
            'firstName' => 'Address',
            'lastName' => 'Import',
            'email' => 'address-import-'.$sourceCustomerId.'@example.test',
            'active' => true,
            'guest' => false,
            'accountType' => 'personal',
            'defaultBillingAddressId' => $billingId,
            'defaultShippingAddressId' => $shippingId,
            'addresses' => [
                ['id' => $billingId, 'firstName' => 'Address', 'lastName' => 'Import', 'street' => 'Billing 1', 'zipcode' => '10115', 'city' => 'Berlin', 'countryId' => $countryId],
                ['id' => $shippingId, 'firstName' => 'Address', 'lastName' => 'Import', 'street' => 'Old shipping 1', 'zipcode' => '10115', 'city' => 'Berlin', 'countryId' => $countryId],
            ],
        ]], $context);
    }

    /** @param EntityRepository<CustomerAddressCollection> $repository */
    private function addressCount(EntityRepository $repository, string $customerId, Context $context): int
    {
        return $repository->search((new Criteria())->addFilter(new EqualsFilter('customerId', $customerId)), $context)->getTotal();
    }

    /** @param EntityRepository<CustomerCollection> $repository */
    private function deleteCustomer(EntityRepository $repository, string $customerId, Context $context): void
    {
        if (null !== $repository->searchIds(new Criteria([$customerId]), $context)->firstId()) {
            $repository->delete([['id' => $customerId]], $context);
        }
    }

    /** @param list<list<int|string>> $rows */
    private function addressFile(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-customer-addresses-');
        self::assertNotFalse($path);
        $stream = fopen($path, 'w');
        self::assertIsResource($stream);
        fputcsv($stream, [
            'source_customer_id', 'source_address_id', 'salutation', 'title', 'first_name', 'last_name',
            'company', 'street', 'zipcode', 'city', 'country', 'phone_number',
        ], ';', '"', '\\', "\n");
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '\\', "\n");
        }
        fclose($stream);

        return $path;
    }
}
