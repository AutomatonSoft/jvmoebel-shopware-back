<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct\CustomerWishlistProductCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ApplyCosmoShopCustomerWishlistsCommandTest extends AbstractCosmoShopImportExportTestCase
{
    public function testDryRunApplyAndRepeatReuseAnExistingWishlistAndCollapseSourceDuplicates(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $sourceCustomerId = 43101;
        $customerId = CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId);
        $otherMarketCustomerId = CosmoShopCustomerIdentity::customerId(Market::Austria, $sourceCustomerId);
        $firstProductNumber = 'WISHLIST-KEEP-001';
        $secondProductNumber = 'WISHLIST-ADD-002';
        $firstProductId = ProductImportIdentity::fromProductNumber($firstProductNumber);
        $secondProductId = ProductImportIdentity::fromProductNumber($secondProductNumber);
        $existingWishlistId = Uuid::randomHex();
        $file = $this->wishlistFile([
            [$sourceCustomerId, 80101, 70101, $firstProductNumber],
            [$sourceCustomerId, 80102, 70101, $firstProductNumber],
            [$sourceCustomerId, 80102, 70102, $secondProductNumber],
        ]);

        $this->createProduct($firstProductNumber, '4260174423609', 'wishlist-keep-001', $context);
        $this->createProduct($secondProductNumber, '4260174423616', 'wishlist-add-002', $context);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureMarketSalesChannel(Market::Austria, $context);
        $this->createCustomer($market, $sourceCustomerId, $context);
        $this->createCustomer(Market::Austria, $sourceCustomerId, $context);

        /** @var EntityRepository<CustomerWishlistCollection> $wishlistRepository */
        $wishlistRepository = static::getContainer()->get('customer_wishlist.repository');
        /** @var EntityRepository<CustomerWishlistProductCollection> $relationRepository */
        $relationRepository = static::getContainer()->get('customer_wishlist_product.repository');
        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');

        $wishlistRepository->create([[
            'id' => $existingWishlistId,
            'customerId' => $customerId,
            'salesChannelId' => $market->salesChannelId(),
            'products' => [[
                'id' => Uuid::randomHex(),
                'productId' => $firstProductId,
                'productVersionId' => Defaults::LIVE_VERSION,
            ]],
        ]], $context);

        try {
            $tester = new CommandTester((new Application(static::getKernel()))->find('jv:cosmoshop:apply-customer-wishlists'));

            self::assertSame(Command::SUCCESS, $tester->execute([
                'market' => $market->domain(),
                'file' => $file,
                '--dry-run' => true,
            ]));
            self::assertSame(1, $this->wishlistProductCount($relationRepository, $existingWishlistId, $context));

            self::assertSame(Command::SUCCESS, $tester->execute(['market' => $market->domain(), 'file' => $file]));
            self::assertSame(2, $this->wishlistProductCount($relationRepository, $existingWishlistId, $context));

            self::assertSame(Command::SUCCESS, $tester->execute(['market' => $market->domain(), 'file' => $file]));
            self::assertSame(2, $this->wishlistProductCount($relationRepository, $existingWishlistId, $context));

            $wishlist = $this->wishlist($wishlistRepository, $customerId, $market, $context);
            self::assertInstanceOf(CustomerWishlistEntity::class, $wishlist);
            self::assertSame($existingWishlistId, $wishlist->getId());
            self::assertNull($this->wishlist($wishlistRepository, $otherMarketCustomerId, Market::Austria, $context));

            $output = $tester->getDisplay(true);
            foreach ([
                'processed=3', 'ready=2', 'wishlists=1', 'written=0', 'existing=2', 'duplicate=1',
                'guest=0', 'source_orphan_product=0', 'missing_customer=0', 'missing_product=0', 'failed=0',
            ] as $counter) {
                self::assertStringContainsString($counter, $output);
            }
            foreach ([(string) $sourceCustomerId, '80101', '70101', $firstProductNumber, $secondProductNumber] as $sensitiveValue) {
                self::assertStringNotContainsString($sensitiveValue, $output);
            }
        } finally {
            $this->deleteCustomer($customerRepository, $customerId, $context);
            $this->deleteCustomer($customerRepository, $otherMarketCustomerId, $context);
            $productRepository->delete([['id' => $firstProductId], ['id' => $secondProductId]], $context);
            unlink($file);
        }
    }

    public function testExpectedSkipsAndTargetGapsDoNotBlockAValidRelationButReturnFailure(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $sourceCustomerId = 43102;
        $customerId = CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId);
        $productNumber = 'WISHLIST-VALID-003';
        $productId = ProductImportIdentity::fromProductNumber($productNumber);
        $file = $this->wishlistFile([
            [$sourceCustomerId, 80201, 70201, $productNumber],
            [0, 80202, 70202, $productNumber],
            [$sourceCustomerId, 80203, 70203, ''],
            [999999, 80204, 70204, $productNumber],
            [$sourceCustomerId, 80205, 70205, 'WISHLIST-MISSING-004'],
            ['not-a-customer', 80206, 70206, 'WISHLIST-MALFORMED-005'],
        ]);

        $this->createProduct($productNumber, '4260174423623', 'wishlist-valid-003', $context);
        $this->ensureMarketSalesChannel($market, $context);
        $this->createCustomer($market, $sourceCustomerId, $context);

        /** @var EntityRepository<CustomerWishlistCollection> $wishlistRepository */
        $wishlistRepository = static::getContainer()->get('customer_wishlist.repository');
        /** @var EntityRepository<CustomerWishlistProductCollection> $relationRepository */
        $relationRepository = static::getContainer()->get('customer_wishlist_product.repository');
        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');

        try {
            $tester = new CommandTester((new Application(static::getKernel()))->find('jv:cosmoshop:apply-customer-wishlists'));
            self::assertSame(Command::FAILURE, $tester->execute(['market' => $market->domain(), 'file' => $file]));

            $wishlist = $this->wishlist($wishlistRepository, $customerId, $market, $context);
            self::assertInstanceOf(CustomerWishlistEntity::class, $wishlist);
            self::assertSame(1, $this->wishlistProductCount($relationRepository, $wishlist->getId(), $context));

            $output = $tester->getDisplay(true);
            foreach ([
                'processed=6', 'ready=1', 'wishlists=1', 'written=1', 'existing=0', 'duplicate=0',
                'guest=1', 'source_orphan_product=1', 'missing_customer=1', 'missing_product=1', 'failed=1',
            ] as $counter) {
                self::assertStringContainsString($counter, $output);
            }
            foreach ([
                (string) $sourceCustomerId, '999999', '80201', '70201', $productNumber,
                'WISHLIST-MISSING-004', 'WISHLIST-MALFORMED-005', 'not-a-customer',
            ] as $sensitiveValue) {
                self::assertStringNotContainsString($sensitiveValue, $output);
            }
        } finally {
            $this->deleteCustomer($customerRepository, $customerId, $context);
            $productRepository->delete([['id' => $productId]], $context);
            unlink($file);
        }
    }

    private function createProduct(string $productNumber, string $ean, string $urlKey, Context $context): void
    {
        $profileId = $this->configureGermanyProfile($context);
        $progress = $this->import($profileId, $this->csv(productNumber: $productNumber, ean: $ean, urlKey: $urlKey));
        self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));
    }

    private function createCustomer(Market $market, int $sourceCustomerId, Context $context): void
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
        $shippingId = CosmoShopCustomerIdentity::shippingAddressId($market, $sourceCustomerId + 900000);

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');
        $customerRepository->create([[
            'id' => $customerId,
            'customerNumber' => $market->domain().'-wishlist-'.$sourceCustomerId,
            'groupId' => $salesChannel->getCustomerGroupId(),
            'salesChannelId' => $market->salesChannelId(),
            'boundSalesChannelId' => $market->salesChannelId(),
            'languageId' => $market->languageId(),
            'firstName' => 'Wishlist',
            'lastName' => 'Import',
            'email' => 'wishlist-import-'.$market->value.'-'.$sourceCustomerId.'@example.test',
            'active' => true,
            'guest' => false,
            'accountType' => 'personal',
            'defaultBillingAddressId' => $billingId,
            'defaultShippingAddressId' => $shippingId,
            'addresses' => [
                ['id' => $billingId, 'firstName' => 'Wishlist', 'lastName' => 'Import', 'street' => 'Billing 1', 'zipcode' => '10115', 'city' => 'Berlin', 'countryId' => $countryId],
                ['id' => $shippingId, 'firstName' => 'Wishlist', 'lastName' => 'Import', 'street' => 'Shipping 1', 'zipcode' => '10115', 'city' => 'Berlin', 'countryId' => $countryId],
            ],
        ]], $context);
    }

    /** @param EntityRepository<CustomerWishlistCollection> $repository */
    private function wishlist(EntityRepository $repository, string $customerId, Market $market, Context $context): ?CustomerWishlistEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customerId', $customerId))
            ->addFilter(new EqualsFilter('salesChannelId', $market->salesChannelId()));

        return $repository->search($criteria, $context)->first();
    }

    /** @param EntityRepository<CustomerWishlistProductCollection> $repository */
    private function wishlistProductCount(EntityRepository $repository, string $wishlistId, Context $context): int
    {
        return $repository->search((new Criteria())->addFilter(new EqualsFilter('wishlistId', $wishlistId)), $context)->getTotal();
    }

    /** @param EntityRepository<CustomerCollection> $repository */
    private function deleteCustomer(EntityRepository $repository, string $customerId, Context $context): void
    {
        if (null !== $repository->searchIds(new Criteria([$customerId]), $context)->firstId()) {
            $repository->delete([['id' => $customerId]], $context);
        }
    }

    /** @param list<list<int|string>> $rows */
    private function wishlistFile(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-customer-wishlists-');
        self::assertNotFalse($path);
        $stream = fopen($path, 'wb');
        self::assertIsResource($stream);
        fputcsv($stream, ['source_customer_id', 'source_list_id', 'source_article_id', 'product_number'], ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '\\');
        }
        fclose($stream);

        return $path;
    }
}
