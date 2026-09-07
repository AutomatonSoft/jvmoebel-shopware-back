<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Profile\CustomerImportProfile;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class CosmoShopCustomerImportTest extends AbstractCosmoShopImportExportTestCase
{
    public function testDryRunValidatesWithoutPersistingTheCustomer(): void
    {
        $context = Context::createDefaultContext();
        $customerId = CosmoShopCustomerIdentity::customerId(Market::Germany, 42000);

        $progress = $this->dryRun(
            $this->configureCustomerProfile(Market::Germany, $context),
            $this->customerCsv(42000),
        );

        self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));

        /** @var EntityRepository<CustomerCollection> $repository */
        $repository = static::getContainer()->get('customer.repository');
        self::assertNull($repository->search(new Criteria([$customerId]), $context)->first());
    }

    public function testItImportsAndUpdatesOneCustomerWithDistinctDefaultAddresses(): void
    {
        $context = Context::createDefaultContext();
        $customerId = CosmoShopCustomerIdentity::customerId(Market::Germany, 42001);

        try {
            $profileId = $this->configureCustomerProfile(Market::Germany, $context);
            $first = $this->import($profileId, $this->customerCsv(42001));
            $second = $this->import($profileId, $this->customerCsv(42001, lastName: 'Updated'));

            self::assertSame(Progress::STATE_SUCCEEDED, $first->getState(), $this->importResult($first));
            self::assertSame(Progress::STATE_SUCCEEDED, $second->getState(), $this->importResult($second));

            /** @var EntityRepository<CustomerCollection> $repository */
            $repository = static::getContainer()->get('customer.repository');
            $customer = $repository->search(
                (new Criteria([$customerId]))
                    ->addAssociation('defaultBillingAddress.country')
                    ->addAssociation('defaultShippingAddress.country'),
                $context,
            )->first();

            self::assertInstanceOf(CustomerEntity::class, $customer);
            self::assertSame('jvmoebel.de-42001', $customer->getCustomerNumber());
            self::assertSame('customer-42001@example.test', $customer->getEmail());
            self::assertSame('Updated', $customer->getLastName());
            self::assertTrue($customer->getActive());
            self::assertFalse($customer->getGuest());
            self::assertSame('business', $customer->getAccountType());
            self::assertSame(['DE123456789'], $customer->getVatIds());
            self::assertSame('amazon', ($customer->getCustomFields() ?? [])['jv_cosmoshop_account_type'] ?? null);
            self::assertSame('1985-02-03', $customer->getBirthday()?->format('Y-m-d'));

            self::assertSame(
                CosmoShopCustomerIdentity::billingAddressId(Market::Germany, 42001),
                $customer->getDefaultBillingAddressId(),
            );
            self::assertSame(
                CosmoShopCustomerIdentity::shippingAddressId(Market::Germany, 92001),
                $customer->getDefaultShippingAddressId(),
            );
            self::assertSame('Main street 12a', $customer->getDefaultBillingAddress()?->getStreet());
            self::assertSame('DE', $customer->getDefaultBillingAddress()?->getCountry()?->getIso());
            self::assertSame('Shipping street 9', $customer->getDefaultShippingAddress()?->getStreet());
            self::assertSame('AT', $customer->getDefaultShippingAddress()?->getCountry()?->getIso());

            self::assertSame(
                1,
                $repository->search(
                    (new Criteria())->addFilter(new EqualsFilter('customerNumber', 'jvmoebel.de-42001')),
                    $context,
                )->getTotal(),
            );

            /** @var EntityRepository<EntityCollection> $addressRepository */
            $addressRepository = static::getContainer()->get('customer_address.repository');
            self::assertSame(
                2,
                $addressRepository->search(
                    (new Criteria())->addFilter(new EqualsFilter('customerId', $customerId)),
                    $context,
                )->getTotal(),
            );
        } finally {
            $this->deleteCustomers([$customerId], $context);
        }
    }

    public function testAnInvalidCustomerDoesNotBlockAValidCustomer(): void
    {
        $context = Context::createDefaultContext();
        $validId = CosmoShopCustomerIdentity::customerId(Market::Germany, 42002);
        $invalidId = CosmoShopCustomerIdentity::customerId(Market::Germany, 42003);
        [$header, $validRow] = explode("\n", $this->customerCsv(42002));
        [, $invalidRow] = explode("\n", $this->customerCsv(42003, email: ''));

        try {
            $progress = $this->import(
                $this->configureCustomerProfile(Market::Germany, $context),
                $header."\n".$validRow."\n".$invalidRow,
            );

            self::assertSame(Progress::STATE_FAILED, $progress->getState(), $this->importResult($progress));

            /** @var EntityRepository<CustomerCollection> $repository */
            $repository = static::getContainer()->get('customer.repository');
            self::assertInstanceOf(CustomerEntity::class, $repository->search(new Criteria([$validId]), $context)->first());
            self::assertNull($repository->search(new Criteria([$invalidId]), $context)->first());
            self::assertStringContainsString('jvmoebel.de-42003', $this->invalidRecordsCsv($progress));
        } finally {
            $this->deleteCustomers([$validId, $invalidId], $context);
        }
    }

    private function configureCustomerProfile(Market $market, Context $context): string
    {
        $this->ensureMarketSalesChannel($market, $context);

        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannel = $salesChannelRepository->search(new Criteria([$market->salesChannelId()]), $context)->first();
        self::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        $definition = CustomerImportProfile::definition($market, $salesChannel->getCustomerGroupId());

        /** @var EntityRepository<EntityCollection<ImportExportProfileEntity>> $profileRepository */
        $profileRepository = static::getContainer()->get('import_export_profile.repository');
        $profileRepository->upsert([$definition], $context);

        return $definition['id'];
    }

    /** @param list<string> $ids */
    private function deleteCustomers(array $ids, Context $context): void
    {
        /** @var EntityRepository<CustomerCollection> $repository */
        $repository = static::getContainer()->get('customer.repository');
        $existingIds = $repository->searchIds(new Criteria($ids), $context)->getIds();
        if ([] === $existingIds) {
            return;
        }

        $repository->delete(array_map(static fn (string $id): array => ['id' => $id], array_values($existingIds)), $context);
    }

    private function customerCsv(int $sourceId, string $lastName = 'Lovelace', ?string $email = null): string
    {
        $billingId = CosmoShopCustomerIdentity::billingAddressId(Market::Germany, $sourceId);
        $shippingId = CosmoShopCustomerIdentity::shippingAddressId(Market::Germany, $sourceId + 50000);
        $email ??= 'customer-'.$sourceId.'@example.test';

        return $this->csvDocument(
            [
                'id', 'customer_number', 'salutation', 'first_name', 'last_name', 'email', 'active', 'guest',
                'customer_group', 'language', 'sales_channel', 'birthday', 'vat_ids', 'custom_fields',
                'billing_id', 'billing_salutation', 'billing_title', 'billing_first_name', 'billing_last_name',
                'billing_company', 'billing_street', 'billing_zipcode', 'billing_city', 'billing_country',
                'billing_phone_number', 'shipping_id', 'shipping_salutation', 'shipping_title',
                'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_street',
                'shipping_zipcode', 'shipping_city', 'shipping_country', 'shipping_phone_number', 'account_type',
            ],
            [[
                CosmoShopCustomerIdentity::customerId(Market::Germany, $sourceId),
                'jvmoebel.de-'.$sourceId,
                'mrs',
                'Ada',
                $lastName,
                $email,
                '1',
                '0',
                '',
                '',
                '',
                '1985-02-03',
                '["DE123456789"]',
                '{"jv_cosmoshop_account_type":"amazon"}',
                $billingId,
                'mrs',
                'Dr.',
                'Ada',
                $lastName,
                'Analytical Engines GmbH',
                'Main street 12a',
                '10115',
                'Berlin',
                'DE',
                '+49 30 123456',
                $shippingId,
                'mr',
                '',
                'Charles',
                'Babbage',
                '',
                'Shipping street 9',
                '1010',
                'Wien',
                'AT',
                '+43 1 123456',
                'business',
            ]],
        );
    }

    /**
     * @param list<string>       $header
     * @param list<list<string>> $rows
     */
    private function csvDocument(array $header, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        fputcsv($stream, $header, ';', '"', '\\', "\n");
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '\\', "\n");
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        self::assertIsString($csv);

        return rtrim($csv, "\n");
    }
}
