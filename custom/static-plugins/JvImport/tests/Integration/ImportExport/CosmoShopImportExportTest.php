<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeCollection;
use Jv\Import\Integration\CosmoShop\CosmoShopProductIdentity;
use Jv\Import\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\Import\Integration\CosmoShop\Reader\CosmoShopCsvPreflightReader;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupData;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupItemData;
use Jv\Import\Service\ProductImport\LookupData\UpsertProductImportLookupDataService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductListRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\AbstractProductSuggestRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final class CosmoShopImportExportTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItDryRunsAValidCosmoShopCsvThroughTheFullImportPipeline(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $progress = $this->dryRun($profileId, $this->csv());

        self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));
        self::assertSame(1, $progress->getProcessedRecords());
        self::assertNull($progress->getInvalidRecordsLogId());
    }

    public function testItSelectsTheCosmoShopPreflightReaderForTheProductProfile(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $path = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-reader-');
        self::assertNotFalse($path);
        file_put_contents($path, $this->csv());

        try {
            $service = static::getContainer()->get(ImportExportService::class);
            self::assertInstanceOf(ImportExportService::class, $service);
            $log = $service->prepareImport(
                $context,
                $profileId,
                new \DateTimeImmutable('+1 day'),
                new UploadedFile($path, 'products.csv', 'text/csv', null, true),
                [],
                true,
            );
        } finally {
            unlink($path);
        }

        $factory = static::getContainer()->get(ImportExportFactory::class);
        self::assertInstanceOf(ImportExportFactory::class, $factory);
        $import = $factory->create($log->getId(), 50, 50);
        $reader = (new \ReflectionProperty(ImportExport::class, 'reader'))->getValue($import);

        self::assertInstanceOf(CosmoShopCsvPreflightReader::class, $reader);
    }

    public function testItCreatesInvalidRecordsForMalformedCosmoShopData(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $progress = $this->dryRun($profileId, $this->csv(stock: '-1'));

        self::assertSame(Progress::STATE_FAILED, $progress->getState());
        self::assertSame(0, $progress->getProcessedRecords());
        self::assertNotNull($progress->getInvalidRecordsLogId());
        self::assertStringContainsString('CosmoShop stock must be a non-negative integer.', $this->importResult($progress));
    }

    public function testItCreatesInvalidRecordsForMissingRequiredEan(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $progress = $this->dryRun($profileId, $this->csv(ean: ''));

        self::assertSame(Progress::STATE_FAILED, $progress->getState());
        self::assertNotNull($progress->getInvalidRecordsLogId());
        self::assertStringContainsString('ean is set to required by the user but has no value', $this->importResult($progress));
    }

    public function testItCreatesAnInvalidRecordForAMalformedRowInTheMiddleOfTheCsv(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        [$header, $firstRow] = explode("\n", $this->csv(), 2);
        $malformedRow = 'MALFORMED-ROW;0;1;4260174423463;0;0;0;0;1;0;119.00';
        $csv = $header."\n".$firstRow."\n".$malformedRow."\n".str_replace('TEST-100034', 'LAST-ROW', $firstRow);

        $progress = $this->dryRun($profileId, $csv);

        self::assertSame(Progress::STATE_FAILED, $progress->getState());
        self::assertStringContainsString('CosmoShop CSV product row has 11 columns; expected 23.', $this->importResult($progress));
    }

    public function testItContinuesAfterAMalformedFirstProductRow(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        [$header, $validRow] = explode("\n", $this->csv(productNumber: 'VALID-AFTER-MALFORMED-001'), 2);
        $malformedRow = 'MALFORMED-FIRST;0;1;4260174423463;0;0;0;0;1;0;119.00';

        $progress = $this->dryRun($profileId, $header."\n".$malformedRow."\n".$validRow);

        self::assertSame(Progress::STATE_FAILED, $progress->getState());
        self::assertSame(1, $progress->getProcessedRecords());
        self::assertStringContainsString('CosmoShop CSV product row has 11 columns; expected 23.', $this->importResult($progress));
    }

    public function testItUsesTheLastRowForTheSameSkuInOneCsv(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $productId = CosmoShopProductIdentity::fromProductNumber('DUPLICATE-SKU-001');
        [$header, $firstRow] = explode("\n", $this->csv(productNumber: 'DUPLICATE-SKU-001'));
        $csv = $header."\n".$firstRow."\n".str_replace('Test product', 'Updated product name', $firstRow);

        try {
            $progress = $this->import($profileId, $csv);

            self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search(new Criteria([$productId]), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame('Updated product name', $product->getName());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItUpdatesOneSharedProductAndAddsTheUnitedKingdomTranslation(): void
    {
        $context = Context::createDefaultContext();
        $productId = CosmoShopProductIdentity::fromProductNumber('SHARED-987654');

        try {
            $german = $this->import(
                $this->configureMarketProfile(Market::Germany, $context),
                $this->csv(productNumber: 'SHARED-987654', name: 'Deutscher Produktname'),
            );
            $british = $this->import(
                $this->configureMarketProfile(Market::UnitedKingdom, $context),
                $this->csv(productNumber: 'SHARED-987654', name: 'English product name'),
            );

            self::assertSame(Progress::STATE_SUCCEEDED, $german->getState(), $this->importResult($german));
            self::assertSame(Progress::STATE_SUCCEEDED, $british->getState(), $this->importResult($british));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search((new Criteria([$productId]))->addAssociation('translations')->addAssociation('visibilities')->addAssociation('manufacturer')->addAssociation('seoUrls')->addAssociation('price'), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame('SHARED-987654', $product->getProductNumber());
            self::assertSame('JVMOEBEL', $product->getManufacturer()?->getName());
            self::assertContains('Deutscher Produktname', array_map(static fn ($translation): ?string => $translation->getName(), $product->getTranslations()->getElements()));
            self::assertContains('English product name', array_map(static fn ($translation): ?string => $translation->getName(), $product->getTranslations()->getElements()));
            self::assertCount(2, $product->getVisibilities());
            self::assertCount(2, $product->getPrice(), json_encode($product->getPrice()->jsonSerialize(), JSON_THROW_ON_ERROR));
            self::assertContains('test-product', array_map(static fn ($seoUrl): string => $seoUrl->getSeoPathInfo(), $product->getSeoUrls()->getElements()));
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItKeepsDistinctGermanTranslationsForGermanyAndAustria(): void
    {
        $context = Context::createDefaultContext();
        $productId = CosmoShopProductIdentity::fromProductNumber('DE-AT-TRANSLATIONS-001');

        try {
            $this->import($this->configureMarketProfile(Market::Germany, $context), $this->csv(productNumber: 'DE-AT-TRANSLATIONS-001', name: 'Deutscher Name'));
            $this->import($this->configureMarketProfile(Market::Austria, $context), $this->csv(productNumber: 'DE-AT-TRANSLATIONS-001', name: 'Österreichischer Name'));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search((new Criteria([$productId]))->addAssociation('translations'), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame('Deutscher Name', $product->getTranslations()->filterByLanguageId(Market::Germany->languageId())->first()?->getName());
            self::assertSame('Österreichischer Name', $product->getTranslations()->filterByLanguageId(Market::Austria->languageId())->first()?->getName());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItStoresDifferentDeliveryTimesForTheSameSkuPerMarket(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'MARKET-DELIVERY-TIME-001';
        $productId = CosmoShopProductIdentity::fromProductNumber($productNumber);
        $crossSellingProductNumber = 'MARKET-DELIVERY-TIME-002';
        $crossSellingProductId = CosmoShopProductIdentity::fromProductNumber($crossSellingProductNumber);
        $references = static::getContainer()->get(UpsertProductImportLookupDataService::class);
        self::assertInstanceOf(UpsertProductImportLookupDataService::class, $references);
        $germanyProfileId = $this->configureMarketProfile(Market::Germany, $context);
        $unitedKingdomProfileId = $this->configureMarketProfile(Market::UnitedKingdom, $context);

        $references->execute(Market::Germany, new ProductImportLookupData(
            [new ProductImportLookupItemData('2', ['de' => 'Lieferzeit: 4-8 Wochen'])],
            [],
        ), $context);
        $references->execute(Market::UnitedKingdom, new ProductImportLookupData(
            [new ProductImportLookupItemData('2', ['en' => 'Delivery time: 6-10 weeks'])],
            [],
        ), $context);

        try {
            self::assertSame(Progress::STATE_SUCCEEDED, $this->import(
                $germanyProfileId,
                $this->csv(productNumber: $productNumber, deliveryTimeId: '2'),
            )->getState());
            self::assertSame(Progress::STATE_SUCCEEDED, $this->import(
                $unitedKingdomProfileId,
                $this->csv(productNumber: $productNumber, deliveryTimeId: '2'),
            )->getState());
            self::assertSame(Progress::STATE_SUCCEEDED, $this->import(
                $unitedKingdomProfileId,
                $this->csv(
                    productNumber: $crossSellingProductNumber,
                    ean: '4260174423464',
                    deliveryTimeId: '2',
                    urlKey: 'market-delivery-time-002',
                ),
            )->getState());

            /** @var EntityRepository<ProductSalesChannelDeliveryTimeCollection> $repository */
            $repository = static::getContainer()->get('jv_import_product_sales_channel_delivery_time.repository');
            $links = $repository->search((new Criteria())->addFilter(new EqualsFilter('productId', $productId)), $context);

            self::assertSame(2, $links->getTotal());
            self::assertSame(
                CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2),
                $links->filterByProperty('salesChannelId', Market::Germany->salesChannelId())->first()?->getDeliveryTimeId(),
            );
            self::assertSame(
                CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2),
                $links->filterByProperty('salesChannelId', Market::UnitedKingdom->salesChannelId())->first()?->getDeliveryTimeId(),
            );
            $route = static::getContainer()->get(ProductDetailRoute::class);
            self::assertInstanceOf(AbstractProductDetailRoute::class, $route);
            $contextFactory = static::getContainer()->get(CachedSalesChannelContextFactory::class);
            self::assertInstanceOf(AbstractSalesChannelContextFactory::class, $contextFactory);

            $germanProduct = $route->load(
                $productId,
                new Request(),
                $contextFactory->create(Uuid::randomHex(), Market::Germany->salesChannelId()),
                new Criteria(),
            )->getProduct();
            $britishProduct = $route->load(
                $productId,
                new Request(),
                $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()),
                new Criteria(),
            )->getProduct();

            $germanDeliveryTimeLinks = $germanProduct->getExtension('jvImportDeliveryTimes');
            $britishDeliveryTimeLinks = $britishProduct->getExtension('jvImportDeliveryTimes');
            self::assertInstanceOf(ProductSalesChannelDeliveryTimeCollection::class, $germanDeliveryTimeLinks);
            self::assertInstanceOf(ProductSalesChannelDeliveryTimeCollection::class, $britishDeliveryTimeLinks);
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $germanDeliveryTimeLinks->first()?->getDeliveryTimeId());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $britishDeliveryTimeLinks->first()?->getDeliveryTimeId());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $germanProduct->getDeliveryTimeId());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $britishProduct->getDeliveryTimeId());
            self::assertSame(
                CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2),
                $this->storeApiDeliveryTimeId($productId, Market::Germany),
            );
            self::assertSame(
                CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2),
                $this->storeApiDeliveryTimeId($productId, Market::UnitedKingdom),
            );

            $searchRoute = static::getContainer()->get(ProductSearchRoute::class);
            self::assertInstanceOf(AbstractProductSearchRoute::class, $searchRoute);
            $britishSearchResult = $searchRoute->load(
                new Request(),
                $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()),
                new Criteria([$productId]),
            )->getListingResult()->first();
            self::assertInstanceOf(ProductEntity::class, $britishSearchResult);
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $britishSearchResult->getDeliveryTimeId());

            $productListRoute = static::getContainer()->get(ProductListRoute::class);
            self::assertInstanceOf(AbstractProductListRoute::class, $productListRoute);
            $britishProductListResult = $productListRoute->load(
                new Criteria([$productId]),
                $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()),
            )->getProducts()->first();
            self::assertInstanceOf(ProductEntity::class, $britishProductListResult);
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $britishProductListResult->getDeliveryTimeId());

            $suggestRoute = static::getContainer()->get(ProductSuggestRoute::class);
            self::assertInstanceOf(AbstractProductSuggestRoute::class, $suggestRoute);
            $britishSuggestResult = $suggestRoute->load(
                new Request(['search' => $productNumber]),
                $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()),
                new Criteria([$productId]),
            )->getListingResult()->first();
            self::assertInstanceOf(ProductEntity::class, $britishSuggestResult);
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $britishSuggestResult->getDeliveryTimeId());

            /** @var EntityRepository<ProductCrossSellingCollection> $crossSellingRepository */
            $crossSellingRepository = static::getContainer()->get('product_cross_selling.repository');
            $crossSellingRepository->create([[
                'id' => Uuid::randomHex(),
                'productId' => $productId,
                'productVersionId' => Defaults::LIVE_VERSION,
                'name' => 'Related products',
                'active' => true,
                'type' => 'productList',
                'position' => 1,
                'limit' => 1,
                'assignedProducts' => [[
                    'id' => Uuid::randomHex(),
                    'productId' => $crossSellingProductId,
                    'productVersionId' => Defaults::LIVE_VERSION,
                    'position' => 1,
                ]],
            ]], $context);
            $crossSellingRoute = static::getContainer()->get(ProductCrossSellingRoute::class);
            self::assertInstanceOf(AbstractProductCrossSellingRoute::class, $crossSellingRoute);
            $britishCrossSellingProduct = $crossSellingRoute->load(
                $productId,
                new Request(),
                $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()),
                new Criteria(),
            )->getResult()->first()?->getProducts()->first();
            self::assertInstanceOf(ProductEntity::class, $britishCrossSellingProduct);
            self::assertSame($crossSellingProductId, $britishCrossSellingProduct->getId());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $britishCrossSellingProduct->getDeliveryTimeId());

            /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
            $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
            $britishSalesChannel = $salesChannelRepository->search(new Criteria([Market::UnitedKingdom->salesChannelId()]), $context)->first();
            self::assertInstanceOf(SalesChannelEntity::class, $britishSalesChannel);
            /** @var EntityRepository<ProductCollection> $productRepository */
            $productRepository = static::getContainer()->get('product.repository');
            $productRepository->update([[
                'id' => $productId,
                'categories' => [['id' => $britishSalesChannel->getNavigationCategoryId()]],
            ]], $context);
            $listingRoute = static::getContainer()->get(ProductListingRoute::class);
            self::assertInstanceOf(AbstractProductListingRoute::class, $listingRoute);
            $britishListingResult = $listingRoute->load(
                $britishSalesChannel->getNavigationCategoryId(),
                new Request(),
                $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()),
                new Criteria(),
            )->getResult()->get($productId);
            self::assertInstanceOf(ProductEntity::class, $britishListingResult);
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $britishListingResult->getDeliveryTimeId());

            $cartService = static::getContainer()->get(CartService::class);
            self::assertInstanceOf(CartService::class, $cartService);
            $britishCartContext = $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId());
            $britishCart = $cartService->createNew($britishCartContext->getToken());
            $britishCart = $cartService->add(
                $britishCart,
                new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId),
                $britishCartContext,
            );
            $britishDeliveryTime = $britishCart->getLineItems()->first()?->getDeliveryInformation()?->getDeliveryTime();
            self::assertNotNull($britishDeliveryTime);
            self::assertSame(6, $britishDeliveryTime->getMin());
            self::assertSame(10, $britishDeliveryTime->getMax());

            $britishLink = $links->filterByProperty('salesChannelId', Market::UnitedKingdom->salesChannelId())->first();
            $repository->delete([['id' => $britishLink->getId()]], $context);

            $britishFallbackProduct = $route->load(
                $productId,
                new Request(),
                $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()),
                new Criteria(),
            )->getProduct();
            self::assertNull($britishFallbackProduct->getExtension('jvImportDeliveryTimes'));
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $britishFallbackProduct->getDeliveryTimeId());

            $britishFallbackResponse = $this->storeApiProduct($productId, Market::UnitedKingdom);
            self::assertArrayNotHasKey('jvImportDeliveryTimes', $britishFallbackResponse['product']['extensions'] ?? []);
            self::assertSame(
                CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2),
                $britishFallbackResponse['product']['deliveryTime']['id'] ?? null,
            );
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId], ['id' => $crossSellingProductId]], $context);
        }
    }

    public function testItPreservesBritishPriceWhenEuroIsImportedAfterwards(): void
    {
        $context = Context::createDefaultContext();
        $productId = CosmoShopProductIdentity::fromProductNumber('GBP-EUR-PRICES-001');

        try {
            $british = $this->import($this->configureMarketProfile(Market::UnitedKingdom, $context), $this->csv(productNumber: 'GBP-EUR-PRICES-001', priceGross: '149.00', urlKey: 'gbp-eur-prices-001'));
            self::assertSame(Progress::STATE_SUCCEEDED, $british->getState(), $this->importResult($british));
            $this->import($this->configureMarketProfile(Market::Germany, $context), $this->csv(productNumber: 'GBP-EUR-PRICES-001', priceGross: '119.00', urlKey: 'gbp-eur-prices-001'));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search((new Criteria([$productId]))->addAssociation('price'), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertCount(2, $product->getPrice(), json_encode($product->getPrice()->jsonSerialize(), JSON_THROW_ON_ERROR));
            self::assertSame(149.0, $this->priceForCurrency($product, 'GBP', $context)->getGross());
            self::assertSame(119.0, $this->priceForCurrency($product, 'EUR', $context)->getGross());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItUpdatesOneCurrencyWithoutCreatingDuplicatesAndKeepsItsListPrice(): void
    {
        $context = Context::createDefaultContext();
        $productId = CosmoShopProductIdentity::fromProductNumber('GBP-REPEAT-PRICE-001');

        try {
            $this->import($this->configureMarketProfile(Market::UnitedKingdom, $context), $this->csv(productNumber: 'GBP-REPEAT-PRICE-001', priceGross: '149.00', listPriceGross: '199.00', urlKey: 'gbp-repeat-price-001'));
            $this->import($this->configureMarketProfile(Market::UnitedKingdom, $context), $this->csv(productNumber: 'GBP-REPEAT-PRICE-001', priceGross: '159.00', listPriceGross: '209.00', urlKey: 'gbp-repeat-price-001'));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search((new Criteria([$productId]))->addAssociation('price'), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertCount(2, $product->getPrice());
            $price = $this->priceForCurrency($product, 'GBP', $context);
            self::assertSame(159.0, $price->getGross());
            self::assertSame(209.0, $price->getListPrice()?->getGross());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItImportsAllCoreCosmoShopProductFields(): void
    {
        $context = Context::createDefaultContext();
        $productId = CosmoShopProductIdentity::fromProductNumber('CORE-FIELDS-001');
        $profileId = $this->configureGermanyProfile($context);
        $references = static::getContainer()->get(UpsertProductImportLookupDataService::class);
        self::assertInstanceOf(UpsertProductImportLookupDataService::class, $references);
        $references->execute(Market::Germany, new ProductImportLookupData(
            [new ProductImportLookupItemData('2', ['de' => 'Lieferzeit: 4-8 Wochen'])],
            [new ProductImportLookupItemData('6', ['de' => 'Stück'])],
        ), $context);

        try {
            $progress = $this->import(
                $profileId,
                $this->csv(
                    productNumber: 'CORE-FIELDS-001',
                    name: 'Produkt mit allen Kernfeldern',
                    ean: '4260174423463',
                    weight: '12.500',
                    length: '220.100',
                    width: '90.200',
                    height: '75.300',
                    minPurchase: '2',
                    maxPurchase: '5',
                    listPriceGross: '238.00',
                    deliveryTimeId: '2',
                    unitId: '6',
                    contents: '1.5',
                    referenceUnit: '1',
                    packUnit: '2',
                ),
            );
            self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search(new Criteria([$productId]), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame('4260174423463', $product->getEan());
            self::assertSame(12.5, $product->getWeight());
            self::assertSame(220.1, $product->getLength());
            self::assertSame(90.2, $product->getWidth());
            self::assertSame(75.3, $product->getHeight());
            self::assertSame(2, $product->getMinPurchase());
            self::assertSame(5, $product->getMaxPurchase());
            self::assertSame(1.5, $product->getPurchaseUnit());
            self::assertSame(1.0, $product->getReferenceUnit());
            self::assertSame('2', $product->getPackUnit());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $product->getDeliveryTimeId());
            self::assertSame(CosmoShopReferenceIdentity::unitId(Market::Germany, 6), $product->getUnitId());
            $price = $product->getPrice()->first();
            self::assertNotNull($price);
            self::assertSame(119.0, $price->getGross());
            self::assertSame(100.0, $price->getNet());
            self::assertSame(238.0, $price->getListPrice()?->getGross());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItUpdatesInsteadOfCreatingAnotherProductWhenTheSameCsvIsRepeated(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'REPEAT-IMPORT-001';
        $productId = CosmoShopProductIdentity::fromProductNumber($productNumber);
        $manufacturerName = 'Idempotent manufacturer '.bin2hex(random_bytes(4));
        $profileId = $this->configureGermanyProfile($context);

        try {
            $first = $this->import($profileId, $this->csv(productNumber: $productNumber, manufacturerName: $manufacturerName));
            $second = $this->import($profileId, $this->csv(productNumber: $productNumber, manufacturerName: $manufacturerName));
            self::assertSame(Progress::STATE_SUCCEEDED, $first->getState(), $this->importResult($first));
            self::assertSame(Progress::STATE_SUCCEEDED, $second->getState(), $this->importResult($second));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $products = $repository->search(
                (new Criteria())->addFilter(new EqualsFilter('productNumber', $productNumber)),
                $context,
            );
            self::assertSame(1, $products->getTotal());
            self::assertSame($productId, $products->first()?->getId());

            /** @var EntityRepository<ProductManufacturerCollection> $manufacturerRepository */
            $manufacturerRepository = static::getContainer()->get('product_manufacturer.repository');
            self::assertSame(
                1,
                $manufacturerRepository->search(
                    (new Criteria())->addFilter(new EqualsFilter('name', $manufacturerName)),
                    $context,
                )->getTotal(),
            );
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItUpdatesAnExistingProductBySkuWhenItsIdIsNotCosmoShopDeterministic(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'EXISTING-RANDOM-ID-001';
        $productId = Uuid::randomHex();

        /** @var EntityRepository<TaxCollection> $taxRepository */
        $taxRepository = static::getContainer()->get('tax.repository');
        $taxId = $taxRepository->searchIds((new Criteria())->setLimit(1), $context)->firstId();
        self::assertNotNull($taxId);

        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');
        $repository->create([[
            'id' => $productId,
            'productNumber' => $productNumber,
            'name' => 'Existing product',
            'stock' => 1,
            'taxId' => $taxId,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'net' => 1.0,
                'gross' => 1.19,
                'linked' => false,
            ]],
        ]], $context);

        try {
            $progress = $this->import(
                $this->configureGermanyProfile($context),
                $this->csv(productNumber: $productNumber, name: 'Updated by SKU'),
            );

            self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));
            $product = $repository->search((new Criteria())->addFilter(new EqualsFilter('productNumber', $productNumber)), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame($productId, $product->getId());
            self::assertSame('Updated by SKU', $product->getName());
        } finally {
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItUsesOneAsTheDefaultMinPurchaseWhenTheCsvValueIsEmpty(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'DEFAULT-MIN-PURCHASE-001';
        $productId = CosmoShopProductIdentity::fromProductNumber($productNumber);

        try {
            $progress = $this->import(
                $this->configureGermanyProfile($context),
                $this->csv(productNumber: $productNumber, minPurchase: ''),
            );

            self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            self::assertSame(1, $repository->search(new Criteria([$productId]), $context)->first()?->getMinPurchase());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    private function dryRun(string $profileId, string $csv): Progress
    {
        return $this->import($profileId, $csv, true);
    }

    private function import(string $profileId, string $csv, bool $dryRun = false): Progress
    {
        $context = Context::createDefaultContext();
        $path = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-import-');
        self::assertNotFalse($path);
        file_put_contents($path, $csv);

        try {
            $service = static::getContainer()->get(ImportExportService::class);
            self::assertInstanceOf(ImportExportService::class, $service);
            $log = $service->prepareImport(
                $context,
                $profileId,
                new \DateTimeImmutable('+1 day'),
                new UploadedFile($path, 'products.csv', 'text/csv', null, true),
                [],
                $dryRun,
            );
        } finally {
            unlink($path);
        }

        $factory = static::getContainer()->get(ImportExportFactory::class);
        self::assertInstanceOf(ImportExportFactory::class, $factory);

        return $factory->create($log->getId(), 50, 50)->import($context);
    }

    private function storeApiDeliveryTimeId(string $productId, Market $market): ?string
    {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannel = $salesChannelRepository->search(new Criteria([$market->salesChannelId()]), Context::createDefaultContext())->first();
        self::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        $response = $this->storeApiProduct($productId, $market);
        self::assertArrayHasKey(
            'jvImportDeliveryTimes',
            $response['product']['extensions'] ?? [],
            json_encode($response, JSON_THROW_ON_ERROR),
        );

        return $response['product']['deliveryTime']['id'] ?? null;
    }

    /** @return array<string, mixed> */
    private function storeApiProduct(string $productId, Market $market): array
    {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannel = $salesChannelRepository->search(new Criteria([$market->salesChannelId()]), Context::createDefaultContext())->first();
        self::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        $browser = KernelLifecycleManager::createBrowser(static::getKernel());
        $browser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_SW_ACCESS_KEY' => $salesChannel->getAccessKey(),
        ]);
        $browser->request('GET', '/store-api/product/'.$productId);

        self::assertTrue($browser->getResponse()->isSuccessful(), $browser->getResponse()->getContent());

        return json_decode((string) $browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function configureGermanyProfile(Context $context): string
    {
        return $this->configureMarketProfile(Market::Germany, $context);
    }

    private function configureMarketProfile(Market $market, Context $context): string
    {
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureMarketCurrency($market, $context);
        $technicalName = MarketImportProfile::technicalName($market);
        $profileId = Uuid::fromStringToHex('jvmoebel.import-profile.'.$technicalName);

        /** @var EntityRepository<EntityCollection<ImportExportProfileEntity>> $repository */
        $repository = static::getContainer()->get('import_export_profile.repository');
        $repository->upsert([[
            'id' => $profileId,
            'technicalName' => $technicalName,
            'type' => ImportExportProfileEntity::TYPE_IMPORT,
            'sourceEntity' => 'product',
            'fileType' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'mapping' => MarketImportProfile::mapping($market),
            'updateBy' => ['id'],
            'config' => [],
        ]], $context);

        return $profileId;
    }

    private function ensureMarketCurrency(Market $market, Context $context): void
    {
        /** @var EntityRepository<CurrencyCollection> $repository */
        $repository = static::getContainer()->get('currency.repository');
        if (null !== $repository->searchIds((new Criteria())->addFilter(new EqualsFilter('isoCode', $market->currencyCode())), $context)->firstId()) {
            return;
        }

        $rounding = ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true];
        $repository->create([[
            'id' => Uuid::fromStringToHex('jv-cosmoshop-import-test-currency-'.$market->currencyCode()),
            'isoCode' => $market->currencyCode(),
            'factor' => 1.0,
            'symbol' => Market::UnitedKingdom === $market ? '£' : $market->currencyCode(),
            'shortName' => $market->currencyCode(),
            'name' => $market->currencyCode(),
            'itemRounding' => $rounding,
            'totalRounding' => $rounding,
        ]], $context);
    }

    private function ensureMarketSalesChannel(Market $market, Context $context): void
    {
        /** @var EntityRepository<SalesChannelCollection> $repository */
        $repository = static::getContainer()->get('sales_channel.repository');
        $existing = $repository->search((new Criteria())->setLimit(1), $context)->first();
        self::assertInstanceOf(SalesChannelEntity::class, $existing);

        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $rootLanguage = $languageRepository->search((new Criteria([$existing->getLanguageId()]))->addAssociation('locale'), $context)->first();
        self::assertNotNull($rootLanguage);
        $languageRepository->upsert([[
            'id' => $market->languageId(),
            'parentId' => $rootLanguage->getId(),
            'name' => 'CosmoShop '.$market->domain(),
            'localeId' => $rootLanguage->getLocaleId(),
            'translationCodeId' => $rootLanguage->getLocaleId(),
            'active' => true,
        ]], $context);

        $repository->upsert([[
            'id' => $market->salesChannelId(),
            'typeId' => $existing->getTypeId(),
            'languageId' => $market->languageId(),
            'customerGroupId' => $existing->getCustomerGroupId(),
            'currencyId' => $existing->getCurrencyId(),
            'paymentMethodId' => $existing->getPaymentMethodId(),
            'shippingMethodId' => $existing->getShippingMethodId(),
            'countryId' => $existing->getCountryId(),
            'navigationCategoryId' => $existing->getNavigationCategoryId(),
            'languages' => [['id' => $market->languageId()]],
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'name' => 'CosmoShop import test',
            'active' => true,
        ]], $context);
    }

    private function importResult(Progress $progress): string
    {
        $service = static::getContainer()->get(ImportExportService::class);
        self::assertInstanceOf(ImportExportService::class, $service);

        $log = $service->findLog(Context::createDefaultContext(), $progress->getLogId());
        $result = json_encode($log->getResult(), JSON_THROW_ON_ERROR);
        $invalidRecordsLogId = $progress->getInvalidRecordsLogId();

        if (null === $invalidRecordsLogId) {
            return $result;
        }

        return $result.' '.$this->invalidRecordsCsv($progress);
    }

    private function invalidRecordsCsv(Progress $progress): string
    {
        $invalidRecordsLogId = $progress->getInvalidRecordsLogId();
        self::assertNotNull($invalidRecordsLogId);
        $service = static::getContainer()->get(ImportExportService::class);
        self::assertInstanceOf(ImportExportService::class, $service);
        $invalidLog = $service->findLog(Context::createDefaultContext(), $invalidRecordsLogId);
        self::assertNotNull($invalidLog->getFile());
        $filesystem = static::getContainer()->get('shopware.filesystem.private');
        self::assertInstanceOf(FilesystemOperator::class, $filesystem);

        return $filesystem->read($invalidLog->getFile()->getPath());
    }

    private function priceForCurrency(ProductEntity $product, string $isoCode, Context $context): \Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price
    {
        /** @var EntityRepository<CurrencyCollection> $repository */
        $repository = static::getContainer()->get('currency.repository');
        $currencyId = $repository->searchIds((new Criteria())->addFilter(new EqualsFilter('isoCode', $isoCode)), $context)->firstId();
        self::assertNotNull($currencyId);
        $price = $product->getPrice()?->getCurrencyPrice($currencyId, false);
        self::assertNotNull($price);

        return $price;
    }

    private function csv(
        string $stock = '0',
        string $productNumber = 'TEST-100034',
        string $name = 'Test product',
        string $ean = '4260174423463',
        string $weight = '0',
        string $length = '0',
        string $width = '0',
        string $height = '0',
        string $minPurchase = '1',
        string $maxPurchase = '0',
        string $listPriceGross = '0',
        string $deliveryTimeId = '',
        string $unitId = '',
        string $contents = '1',
        string $referenceUnit = '1',
        string $packUnit = 'Stück',
        string $manufacturerName = 'JVMOEBEL',
        string $priceGross = '119.00',
        string $urlKey = 'test-product',
    ): string {
        return implode("\n", [
            'product_number;source_inactive;stock;ean;weight;length;width;height;min_purchase;max_purchase;price_gross;name;description;short_description;keywords;manufacturer_name;list_price_gross;delivery_time_id;unit_id;contents;reference_unit;pack_unit;urlkey',
            $productNumber.';0;'.$stock.';'.$ean.';'.$weight.';'.$length.';'.$width.';'.$height.';'.$minPurchase.';'.$maxPurchase.';'.$priceGross.';'.$name.';<p>description</p>;Short description;keyword;'.$manufacturerName.';'.$listPriceGross.';'.$deliveryTimeId.';'.$unitId.';'.$contents.';'.$referenceUnit.';'.$packUnit.';'.$urlKey,
        ]);
    }
}
