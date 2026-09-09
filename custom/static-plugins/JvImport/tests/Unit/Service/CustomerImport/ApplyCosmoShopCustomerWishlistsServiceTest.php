<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\CustomerImport;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerWishlistCsvReader;
use Jv\Import\Service\CustomerImport\ApplyCosmoShopCustomerWishlistsService;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct\CustomerWishlistProductCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Uuid\Uuid;

final class ApplyCosmoShopCustomerWishlistsServiceTest extends TestCase
{
    public function testItBatchesTwoHundredAndFiftyOneLookupsAndWrites(): void
    {
        $market = Market::Germany;
        $file = $this->file(array_map(static fn (int $id): array => [$id, $id, $id, 'WISHLIST-BATCH-'.$id], range(1, 251)));
        $customers = [];
        $products = [];
        foreach (range(1, 251) as $id) {
            $customer = new CustomerEntity();
            $customer->setId(CosmoShopCustomerIdentity::customerId($market, $id));
            $customer->setSalesChannelId($market->salesChannelId());
            $customers[] = $customer;
            $product = new ProductEntity();
            $product->setId(ProductImportIdentity::fromProductNumber('WISHLIST-BATCH-'.$id));
            $products[] = $product;
        }

        /** @var EntityRepository<CustomerCollection>&MockObject $customerRepository */
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerLookups = [];
        $customerRepository->expects(self::exactly(2))->method('search')->willReturnCallback(function (Criteria $criteria, Context $context) use ($customers, &$customerLookups): EntitySearchResult {
            $customerLookups[] = count($criteria->getIds());

            return $this->searchResult('customer', new CustomerCollection($customers), $criteria, $context);
        });
        /** @var EntityRepository<ProductCollection>&MockObject $productRepository */
        $productRepository = $this->createMock(EntityRepository::class);
        $productLookups = [];
        $productRepository->expects(self::exactly(2))->method('search')->willReturnCallback(function (Criteria $criteria, Context $context) use ($products, &$productLookups): EntitySearchResult {
            $productLookups[] = count($criteria->getIds());

            return $this->searchResult('product', new ProductCollection($products), $criteria, $context);
        });
        /** @var EntityRepository<CustomerWishlistCollection>&MockObject $wishlistRepository */
        $wishlistRepository = $this->createMock(EntityRepository::class);
        $wishlistLookups = [];
        $wishlistWrites = [];
        $wishlistRepository->expects(self::exactly(4))->method('search')->willReturnCallback(function (Criteria $criteria, Context $context) use (&$wishlistLookups): EntitySearchResult {
            $filter = $criteria->getFilters()[0] ?? null;
            if (!$filter instanceof EqualsAnyFilter) {
                throw new \LogicException('Expected customer wishlist lookup filter.');
            }
            $wishlistLookups[] = count($filter->getValue());

            return $this->searchResult('customer_wishlist', new CustomerWishlistCollection(), $criteria, $context);
        });
        $wishlistRepository->expects(self::exactly(2))->method('upsert')->willReturnCallback(static function (array $records, Context $context) use (&$wishlistWrites): EntityWrittenContainerEvent {
            $wishlistWrites[] = count($records);

            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        });
        /** @var EntityRepository<CustomerWishlistProductCollection>&MockObject $relationRepository */
        $relationRepository = $this->createMock(EntityRepository::class);
        $relationLookups = [];
        $relationWrites = [];
        $relationRepository->expects(self::exactly(2))->method('search')->willReturnCallback(function (Criteria $criteria, Context $context) use (&$relationLookups): EntitySearchResult {
            $filter = $criteria->getFilters()[0] ?? null;
            if (!$filter instanceof EqualsAnyFilter) {
                throw new \LogicException('Expected customer wishlist product lookup filter.');
            }
            $relationLookups[] = count($filter->getValue());

            return $this->searchResult('customer_wishlist_product', new CustomerWishlistProductCollection(), $criteria, $context);
        });
        $relationRepository->expects(self::exactly(2))->method('upsert')->willReturnCallback(static function (array $records, Context $context) use (&$relationWrites): EntityWrittenContainerEvent {
            $relationWrites[] = count($records);

            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        });

        try {
            $result = (new ApplyCosmoShopCustomerWishlistsService(new CosmoShopCustomerWishlistCsvReader(), $customerRepository, $productRepository, $wishlistRepository, $relationRepository))
                ->execute($market, $file, false, Context::createDefaultContext());

            self::assertSame(251, $result->ready);
            self::assertSame(251, $result->wishlists);
            self::assertSame(251, $result->written);
            foreach ([$customerLookups, $productLookups, $relationLookups, $wishlistWrites, $relationWrites] as $sizes) {
                self::assertSame([250, 1], $sizes);
            }
            self::assertSame([250, 1, 250, 1], $wishlistLookups);
        } finally {
            unlink($file);
        }
    }

    public function testItIsolatesARejectedRelationWithoutRetryingOtherInfrastructureFailures(): void
    {
        $market = Market::Germany;
        $sourceId = 551;
        $number = 'WISHLIST-REJECTED';
        $file = $this->file([[$sourceId, 1, 1, $number]]);
        $customer = new CustomerEntity();
        $customer->setId(CosmoShopCustomerIdentity::customerId($market, $sourceId));
        $customer->setSalesChannelId($market->salesChannelId());
        $product = new ProductEntity();
        $product->setId(ProductImportIdentity::fromProductNumber($number));
        $wishlist = new CustomerWishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setCustomerId($customer->getId());
        $wishlist->setSalesChannelId($market->salesChannelId());

        /** @var EntityRepository<CustomerCollection>&MockObject $customerRepository */
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer', new CustomerCollection([$customer]), $criteria, $context));
        /** @var EntityRepository<ProductCollection>&MockObject $productRepository */
        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('product', new ProductCollection([$product]), $criteria, $context));
        /** @var EntityRepository<CustomerWishlistCollection>&MockObject $wishlistRepository */
        $wishlistRepository = $this->createMock(EntityRepository::class);
        $wishlistRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer_wishlist', new CustomerWishlistCollection([$wishlist]), $criteria, $context));
        /** @var EntityRepository<CustomerWishlistProductCollection>&MockObject $relationRepository */
        $relationRepository = $this->createMock(EntityRepository::class);
        $relationRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer_wishlist_product', new CustomerWishlistProductCollection(), $criteria, $context));
        $relationRepository->expects(self::once())->method('upsert')->willThrowException((new WriteException())->add(new \InvalidArgumentException('private synthetic value')));

        try {
            $result = (new ApplyCosmoShopCustomerWishlistsService(new CosmoShopCustomerWishlistCsvReader(), $customerRepository, $productRepository, $wishlistRepository, $relationRepository))
                ->execute($market, $file, false, Context::createDefaultContext());
            self::assertSame(0, $result->written);
            self::assertSame(1, $result->failed);
            self::assertSame([WriteException::class], $result->exceptionClasses());
        } finally {
            unlink($file);
        }
    }

    public function testItRejectsACustomerFromAnotherSalesChannel(): void
    {
        $market = Market::Germany;
        $sourceId = 552;
        $number = 'WISHLIST-FOREIGN-CUSTOMER';
        $file = $this->file([[$sourceId, 1, 1, $number]]);
        $customer = new CustomerEntity();
        $customer->setId(CosmoShopCustomerIdentity::customerId($market, $sourceId));
        $customer->setSalesChannelId(Market::Austria->salesChannelId());
        $product = new ProductEntity();
        $product->setId(ProductImportIdentity::fromProductNumber($number));

        /** @var EntityRepository<CustomerCollection>&MockObject $customerRepository */
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer', new CustomerCollection([$customer]), $criteria, $context));
        /** @var EntityRepository<ProductCollection>&MockObject $productRepository */
        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('product', new ProductCollection([$product]), $criteria, $context));
        /** @var EntityRepository<CustomerWishlistCollection>&MockObject $wishlistRepository */
        $wishlistRepository = $this->createMock(EntityRepository::class);
        $wishlistRepository->expects(self::never())->method('search');
        /** @var EntityRepository<CustomerWishlistProductCollection>&MockObject $relationRepository */
        $relationRepository = $this->createMock(EntityRepository::class);
        $relationRepository->expects(self::never())->method('search');

        try {
            $result = (new ApplyCosmoShopCustomerWishlistsService(new CosmoShopCustomerWishlistCsvReader(), $customerRepository, $productRepository, $wishlistRepository, $relationRepository))
                ->execute($market, $file, true, Context::createDefaultContext());
            self::assertSame(0, $result->ready);
            self::assertSame(1, $result->missingCustomer);
        } finally {
            unlink($file);
        }
    }

    public function testItDoesNotWriteRelationsForAWishlistWhoseCreationFailed(): void
    {
        $market = Market::Germany;
        $file = $this->file([[601, 1, 1, 'WISHLIST-CREATE-ONE'], [602, 2, 2, 'WISHLIST-CREATE-TWO']]);
        $customers = [];
        $products = [];
        foreach ([[601, 'WISHLIST-CREATE-ONE'], [602, 'WISHLIST-CREATE-TWO']] as [$sourceId, $number]) {
            $customer = new CustomerEntity();
            $customer->setId(CosmoShopCustomerIdentity::customerId($market, $sourceId));
            $customer->setSalesChannelId($market->salesChannelId());
            $customers[] = $customer;
            $product = new ProductEntity();
            $product->setId(ProductImportIdentity::fromProductNumber($number));
            $products[] = $product;
        }
        $persisted = new CustomerWishlistEntity();
        $persisted->setId(Uuid::randomHex());
        $persisted->setCustomerId($customers[0]->getId());
        $persisted->setSalesChannelId($market->salesChannelId());

        /** @var EntityRepository<CustomerCollection>&MockObject $customerRepository */
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer', new CustomerCollection($customers), $criteria, $context));
        /** @var EntityRepository<ProductCollection>&MockObject $productRepository */
        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('product', new ProductCollection($products), $criteria, $context));
        /** @var EntityRepository<CustomerWishlistCollection>&MockObject $wishlistRepository */
        $wishlistRepository = $this->createMock(EntityRepository::class);
        $wishlistSearches = 0;
        $wishlistRepository->method('search')->willReturnCallback(function (Criteria $criteria, Context $context) use (&$wishlistSearches, $persisted): EntitySearchResult {
            ++$wishlistSearches;

            return $this->searchResult('customer_wishlist', new CustomerWishlistCollection(1 === $wishlistSearches ? [] : [$persisted]), $criteria, $context);
        });
        $wishlistWrites = 0;
        $wishlistRepository->expects(self::exactly(3))->method('upsert')->willReturnCallback(static function (array $records, Context $context) use (&$wishlistWrites): EntityWrittenContainerEvent {
            ++$wishlistWrites;
            if (1 === $wishlistWrites || 3 === $wishlistWrites) {
                throw (new WriteException())->add(new \InvalidArgumentException('private synthetic value'));
            }

            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        });
        /** @var EntityRepository<CustomerWishlistProductCollection>&MockObject $relationRepository */
        $relationRepository = $this->createMock(EntityRepository::class);
        $relationRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer_wishlist_product', new CustomerWishlistProductCollection(), $criteria, $context));
        $relationRepository->expects(self::once())->method('upsert')->willReturnCallback(static function (array $records, Context $context): EntityWrittenContainerEvent {
            self::assertCount(1, $records);

            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        });

        try {
            $result = (new ApplyCosmoShopCustomerWishlistsService(new CosmoShopCustomerWishlistCsvReader(), $customerRepository, $productRepository, $wishlistRepository, $relationRepository))
                ->execute($market, $file, false, Context::createDefaultContext());
            self::assertSame(1, $result->written);
            self::assertSame(1, $result->failed);
        } finally {
            unlink($file);
        }
    }

    public function testItDoesNotRetryAnInfrastructureRelationWriteFailure(): void
    {
        $market = Market::Germany;
        $sourceId = 603;
        $number = 'WISHLIST-INFRASTRUCTURE';
        $file = $this->file([[$sourceId, 1, 1, $number]]);
        $customer = new CustomerEntity();
        $customer->setId(CosmoShopCustomerIdentity::customerId($market, $sourceId));
        $customer->setSalesChannelId($market->salesChannelId());
        $product = new ProductEntity();
        $product->setId(ProductImportIdentity::fromProductNumber($number));
        $wishlist = new CustomerWishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setCustomerId($customer->getId());
        $wishlist->setSalesChannelId($market->salesChannelId());

        /** @var EntityRepository<CustomerCollection>&MockObject $customerRepository */
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer', new CustomerCollection([$customer]), $criteria, $context));
        /** @var EntityRepository<ProductCollection>&MockObject $productRepository */
        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('product', new ProductCollection([$product]), $criteria, $context));
        /** @var EntityRepository<CustomerWishlistCollection>&MockObject $wishlistRepository */
        $wishlistRepository = $this->createMock(EntityRepository::class);
        $wishlistRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer_wishlist', new CustomerWishlistCollection([$wishlist]), $criteria, $context));
        /** @var EntityRepository<CustomerWishlistProductCollection>&MockObject $relationRepository */
        $relationRepository = $this->createMock(EntityRepository::class);
        $relationRepository->method('search')->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->searchResult('customer_wishlist_product', new CustomerWishlistProductCollection(), $criteria, $context));
        $failure = new \RuntimeException('Database unavailable.');
        $relationRepository->expects(self::once())->method('upsert')->willThrowException($failure);

        try {
            $this->expectExceptionObject($failure);
            (new ApplyCosmoShopCustomerWishlistsService(new CosmoShopCustomerWishlistCsvReader(), $customerRepository, $productRepository, $wishlistRepository, $relationRepository))
                ->execute($market, $file, false, Context::createDefaultContext());
        } finally {
            unlink($file);
        }
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
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-wishlist-service-');
        self::assertNotFalse($file);
        $stream = fopen($file, 'wb');
        self::assertIsResource($stream);
        fputcsv($stream, ['source_customer_id', 'source_list_id', 'source_article_id', 'product_number'], ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '\\');
        }
        fclose($stream);

        return $file;
    }
}
