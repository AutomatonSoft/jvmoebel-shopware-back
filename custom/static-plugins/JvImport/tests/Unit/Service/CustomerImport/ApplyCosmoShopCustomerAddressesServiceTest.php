<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\CustomerImport;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerAddressCsvReader;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Service\CustomerImport\ApplyCosmoShopCustomerAddressesService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;

final class ApplyCosmoShopCustomerAddressesServiceTest extends TestCase
{
    public function testItBatchesTwoHundredAndFiftyOneLookupsAndWrites(): void
    {
        $market = Market::Germany;
        $file = $this->file(array_map(
            static fn (int $sourceId): array => [$sourceId, $sourceId + 1000, 'mr', '', 'First', 'Last', '', 'Street 1', '', 'Berlin', 'DE', ''],
            range(1, 251),
        ));
        $customers = [];
        foreach (range(1, 251) as $sourceId) {
            $customer = new CustomerEntity();
            $customer->setId(CosmoShopCustomerIdentity::customerId($market, $sourceId));
            $customers[$customer->getId()] = $customer;
        }

        /** @var EntityRepository<CustomerCollection>&MockObject $customerRepository */
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerLookupSizes = [];
        $customerRepository->expects(self::exactly(2))->method('search')->willReturnCallback(
            function (Criteria $criteria, Context $context) use ($customers, &$customerLookupSizes): EntitySearchResult {
                $customerLookupSizes[] = count($criteria->getIds());
                $entities = array_values(array_intersect_key($customers, array_flip($criteria->getIds())));

                return $this->searchResult('customer', new CustomerCollection($entities), $criteria, $context);
            },
        );
        /** @var EntityRepository<CustomerAddressCollection>&MockObject $addressRepository */
        $addressRepository = $this->createMock(EntityRepository::class);
        $addressLookupSizes = [];
        $addressRepository->expects(self::exactly(2))->method('search')->willReturnCallback(
            function (Criteria $criteria, Context $context) use (&$addressLookupSizes): EntitySearchResult {
                $addressLookupSizes[] = count($criteria->getIds());

                return $this->searchResult('customer_address', new CustomerAddressCollection(), $criteria, $context);
            },
        );
        $writeSizes = [];
        $addressRepository->expects(self::exactly(2))->method('upsert')->willReturnCallback(
            static function (array $records, Context $context) use (&$writeSizes): EntityWrittenContainerEvent {
                $writeSizes[] = count($records);

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );

        [$countryRepository, $salutationRepository] = $this->referenceRepositories();

        try {
            $result = (new ApplyCosmoShopCustomerAddressesService(
                new CosmoShopCustomerAddressCsvReader(),
                $customerRepository,
                $addressRepository,
                $countryRepository,
                $salutationRepository,
            ))->execute($market, $file, false, Context::createDefaultContext());

            self::assertSame(251, $result->ready);
            self::assertSame(251, $result->written);
            self::assertSame([250, 1], $customerLookupSizes);
            self::assertSame([250, 1], $addressLookupSizes);
            self::assertSame([250, 1], $writeSizes);
        } finally {
            unlink($file);
        }
    }

    public function testItRejectsAnAddressIdentityOwnedByAnotherCustomer(): void
    {
        $market = Market::Germany;
        $sourceCustomerId = 42;
        $sourceAddressId = 17;
        $file = $this->file([[$sourceCustomerId, $sourceAddressId, 'mr', '', 'First', 'Last', '', 'Street 1', '', 'Berlin', 'DE', '']]);
        $customer = new CustomerEntity();
        $customer->setId(CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId));
        $address = new CustomerAddressEntity();
        $address->setId(CosmoShopCustomerIdentity::shippingAddressId($market, $sourceAddressId));
        $address->setCustomerId(Uuid::randomHex());

        /** @var EntityRepository<CustomerCollection>&MockObject $customerRepository */
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer', new CustomerCollection([$customer]), $criteria, $context),
        );
        /** @var EntityRepository<CustomerAddressCollection>&MockObject $addressRepository */
        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer_address', new CustomerAddressCollection([$address]), $criteria, $context),
        );
        $addressRepository->expects(self::never())->method('upsert');
        [$countryRepository, $salutationRepository] = $this->referenceRepositories();

        try {
            $result = (new ApplyCosmoShopCustomerAddressesService(new CosmoShopCustomerAddressCsvReader(), $customerRepository, $addressRepository, $countryRepository, $salutationRepository))
                ->execute($market, $file, false, Context::createDefaultContext());

            self::assertSame(0, $result->ready);
            self::assertSame(0, $result->written);
            self::assertSame(1, $result->failed);
        } finally {
            unlink($file);
        }
    }

    public function testItFallsBackPerRowSoAValidAddressSurvivesAWriteInvalidNeighbor(): void
    {
        $market = Market::Germany;
        $file = $this->file([
            [51, 61, 'mr', '', 'Valid', 'Recipient', '', 'Valid street 1', '', 'Berlin', 'DE', ''],
            [52, 62, 'mr', '', 'Write', 'Invalid', '', 'Rejected street 2', '', 'Berlin', 'DE', ''],
        ]);
        $customers = [];
        foreach ([51, 52] as $sourceId) {
            $customer = new CustomerEntity();
            $customer->setId(CosmoShopCustomerIdentity::customerId($market, $sourceId));
            $customers[] = $customer;
        }

        /** @var EntityRepository<CustomerCollection>&MockObject $customerRepository */
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer', new CustomerCollection($customers), $criteria, $context),
        );
        /** @var EntityRepository<CustomerAddressCollection>&MockObject $addressRepository */
        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer_address', new CustomerAddressCollection(), $criteria, $context),
        );
        $addressRepository->expects(self::exactly(3))->method('upsert')->willReturnCallback(
            static function (array $records, Context $context): EntityWrittenContainerEvent {
                if (count($records) > 1 || 'Rejected street 2' === $records[0]['street']) {
                    throw (new WriteException())->add(new \InvalidArgumentException('Synthetic row content must stay private.'));
                }

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );
        [$countryRepository, $salutationRepository] = $this->referenceRepositories();

        try {
            $result = (new ApplyCosmoShopCustomerAddressesService(new CosmoShopCustomerAddressCsvReader(), $customerRepository, $addressRepository, $countryRepository, $salutationRepository))
                ->execute($market, $file, false, Context::createDefaultContext());

            self::assertSame(2, $result->ready);
            self::assertSame(1, $result->written);
            self::assertSame(1, $result->failed);
            self::assertSame([WriteException::class], $result->exceptionClasses());
        } finally {
            unlink($file);
        }
    }

    public function testItDoesNotRetryAnInfrastructureWriteFailure(): void
    {
        $market = Market::Germany;
        $rows = array_map(
            static fn (int $sourceId): array => [$sourceId, $sourceId + 2000, 'mr', '', 'First', 'Last', '', 'Street 1', '', 'Berlin', 'DE', ''],
            range(1, 50),
        );
        $file = $this->file($rows);
        $customers = [];
        foreach (range(1, 50) as $sourceId) {
            $customer = new CustomerEntity();
            $customer->setId(CosmoShopCustomerIdentity::customerId($market, $sourceId));
            $customers[] = $customer;
        }

        /** @var EntityRepository<CustomerCollection>&MockObject $customerRepository */
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer', new CustomerCollection($customers), $criteria, $context),
        );
        /** @var EntityRepository<CustomerAddressCollection>&MockObject $addressRepository */
        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer_address', new CustomerAddressCollection(), $criteria, $context),
        );
        $failure = new \RuntimeException('Database connection unavailable.');
        $addressRepository->expects(self::once())->method('upsert')->willThrowException($failure);
        [$countryRepository, $salutationRepository] = $this->referenceRepositories();

        try {
            $this->expectExceptionObject($failure);

            (new ApplyCosmoShopCustomerAddressesService(new CosmoShopCustomerAddressCsvReader(), $customerRepository, $addressRepository, $countryRepository, $salutationRepository))
                ->execute($market, $file, false, Context::createDefaultContext());
        } finally {
            unlink($file);
        }
    }

    /** @return array{EntityRepository<CountryCollection>&MockObject, EntityRepository<SalutationCollection>&MockObject} */
    private function referenceRepositories(): array
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setIso('DE');
        /** @var EntityRepository<CountryCollection>&MockObject $countryRepository */
        $countryRepository = $this->createMock(EntityRepository::class);
        $countryRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('country', new CountryCollection([$country]), $criteria, $context),
        );

        $salutation = new SalutationEntity();
        $salutation->setId(Uuid::randomHex());
        $salutation->setSalutationKey('mr');
        /** @var EntityRepository<SalutationCollection>&MockObject $salutationRepository */
        $salutationRepository = $this->createMock(EntityRepository::class);
        $salutationRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('salutation', new SalutationCollection([$salutation]), $criteria, $context),
        );

        return [$countryRepository, $salutationRepository];
    }

    /**
     * @template TCollection of EntityCollection
     *
     * @param TCollection $collection
     *
     * @return EntitySearchResult<TCollection>
     */
    private function searchResult(string $entity, EntityCollection $collection, Criteria $criteria, Context $context): EntitySearchResult
    {
        return new EntitySearchResult($entity, $collection->count(), $collection, null, $criteria, $context);
    }

    /** @param list<list<int|string>> $rows */
    private function file(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-address-service-');
        self::assertNotFalse($file);
        $stream = fopen($file, 'wb');
        self::assertIsResource($stream);
        fputcsv($stream, [
            'source_customer_id', 'source_address_id', 'salutation', 'title', 'first_name', 'last_name',
            'company', 'street', 'zipcode', 'city', 'country', 'phone_number',
        ], ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '\\');
        }
        fclose($stream);

        return $file;
    }
}
