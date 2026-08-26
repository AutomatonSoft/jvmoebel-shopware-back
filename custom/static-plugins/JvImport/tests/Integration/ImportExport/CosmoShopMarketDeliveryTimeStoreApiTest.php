<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeCollection;
use Jv\Import\Integration\CosmoShop\CosmoShopProductIdentity;
use Jv\Import\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupData;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupItemData;
use Jv\Import\Service\ProductImport\LookupData\UpsertProductImportLookupDataService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;

final class CosmoShopMarketDeliveryTimeStoreApiTest extends AbstractCosmoShopImportExportTestCase
{
    public function testItUsesTheMarketDeliveryTimeAcrossProductStoreApiRoutes(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'MARKET-DELIVERY-STORE-API-001';
        $productId = CosmoShopProductIdentity::fromProductNumber($productNumber);
        $variantProductId = Uuid::randomHex();
        $crossSellingProductNumber = 'MARKET-DELIVERY-STORE-API-002';
        $crossSellingProductId = CosmoShopProductIdentity::fromProductNumber($crossSellingProductNumber);
        $references = static::getContainer()->get(UpsertProductImportLookupDataService::class);
        self::assertInstanceOf(UpsertProductImportLookupDataService::class, $references);
        $germanyProfileId = $this->configureMarketProfile(Market::Germany, $context);
        $unitedKingdomProfileId = $this->configureMarketProfile(Market::UnitedKingdom, $context);
        $references->execute(Market::Germany, new ProductImportLookupData([new ProductImportLookupItemData('2', ['de' => 'Lieferzeit: 4-8 Wochen'])], []), $context);
        $references->execute(Market::UnitedKingdom, new ProductImportLookupData([
            new ProductImportLookupItemData('2', ['en' => 'Delivery time: 6-10 weeks']),
            new ProductImportLookupItemData('3', ['en' => 'Delivery time: 2-3 weeks']),
        ], []), $context);

        try {
            self::assertSame(Progress::STATE_SUCCEEDED, $this->import($germanyProfileId, $this->csv(productNumber: $productNumber, deliveryTimeId: '2'))->getState());
            self::assertSame(Progress::STATE_SUCCEEDED, $this->import($unitedKingdomProfileId, $this->csv(productNumber: $productNumber, deliveryTimeId: '2'))->getState());
            self::assertSame(Progress::STATE_SUCCEEDED, $this->import($unitedKingdomProfileId, $this->csv(productNumber: $crossSellingProductNumber, ean: '4260174423464', deliveryTimeId: '2', urlKey: 'market-delivery-store-api-002'))->getState());

            /** @var EntityRepository<ProductSalesChannelDeliveryTimeCollection> $deliveryTimeRepository */
            $deliveryTimeRepository = static::getContainer()->get('jv_import_product_sales_channel_delivery_time.repository');
            $links = $deliveryTimeRepository->search((new Criteria())->addFilter(new EqualsFilter('productId', $productId)), $context);
            self::assertSame(2, $links->getTotal());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $links->filterByProperty('salesChannelId', Market::Germany->salesChannelId())->first()?->getDeliveryTimeId());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $links->filterByProperty('salesChannelId', Market::UnitedKingdom->salesChannelId())->first()?->getDeliveryTimeId());

            $contextFactory = static::getContainer()->get(CachedSalesChannelContextFactory::class);
            self::assertInstanceOf(AbstractSalesChannelContextFactory::class, $contextFactory);
            $detailRoute = static::getContainer()->get(ProductDetailRoute::class);
            self::assertInstanceOf(AbstractProductDetailRoute::class, $detailRoute);
            $germanProduct = $detailRoute->load($productId, new Request(), $contextFactory->create(Uuid::randomHex(), Market::Germany->salesChannelId()), new Criteria())->getProduct();
            $britishProduct = $detailRoute->load($productId, new Request(), $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()), new Criteria())->getProduct();
            self::assertNull($germanProduct->getExtension('jvImportDeliveryTimes'));
            self::assertNull($britishProduct->getExtension('jvImportDeliveryTimes'));
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $germanProduct->getDeliveryTimeId());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $britishProduct->getDeliveryTimeId());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $this->storeApiDeliveryTimeId($productId, Market::Germany));
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $this->storeApiDeliveryTimeId($productId, Market::UnitedKingdom));
            $searchRoute = static::getContainer()->get(ProductSearchRoute::class);
            self::assertInstanceOf(AbstractProductSearchRoute::class, $searchRoute);
            $searchProduct = $searchRoute->load(new Request(), $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()), new Criteria([$productId]))->getListingResult()->first();
            self::assertInstanceOf(ProductEntity::class, $searchProduct);
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $searchProduct->getDeliveryTimeId());

            $productListRoute = static::getContainer()->get(ProductListRoute::class);
            self::assertInstanceOf(\Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute::class, $productListRoute);
            $listProduct = $productListRoute->load(new Criteria([$productId]), $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()))->getProducts()->first();
            self::assertInstanceOf(ProductEntity::class, $listProduct);
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $listProduct->getDeliveryTimeId());

            $suggestRoute = static::getContainer()->get(ProductSuggestRoute::class);
            self::assertInstanceOf(AbstractProductSuggestRoute::class, $suggestRoute);
            $suggestProduct = $suggestRoute->load(new Request(['search' => $productNumber]), $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()), new Criteria([$productId]))->getListingResult()->first();
            self::assertInstanceOf(ProductEntity::class, $suggestProduct);
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $suggestProduct->getDeliveryTimeId());

            /** @var EntityRepository<ProductCrossSellingCollection> $crossSellingRepository */
            $crossSellingRepository = static::getContainer()->get('product_cross_selling.repository');
            $crossSellingRepository->create([['id' => Uuid::randomHex(), 'productId' => $productId, 'productVersionId' => Defaults::LIVE_VERSION, 'name' => 'Related products', 'active' => true, 'type' => 'productList', 'position' => 1, 'limit' => 1, 'assignedProducts' => [['id' => Uuid::randomHex(), 'productId' => $crossSellingProductId, 'productVersionId' => Defaults::LIVE_VERSION, 'position' => 1]]]], $context);
            $crossSellingRoute = static::getContainer()->get(ProductCrossSellingRoute::class);
            self::assertInstanceOf(AbstractProductCrossSellingRoute::class, $crossSellingRoute);
            $crossSellingProduct = $crossSellingRoute->load($productId, new Request(), $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()), new Criteria())->getResult()->first()?->getProducts()->first();
            self::assertInstanceOf(ProductEntity::class, $crossSellingProduct);
            self::assertSame($crossSellingProductId, $crossSellingProduct->getId());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $crossSellingProduct->getDeliveryTimeId());

            /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
            $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
            $britishSalesChannel = $salesChannelRepository->search(new Criteria([Market::UnitedKingdom->salesChannelId()]), $context)->first();
            self::assertInstanceOf(SalesChannelEntity::class, $britishSalesChannel);
            /** @var EntityRepository<ProductCollection> $productRepository */
            $productRepository = static::getContainer()->get('product.repository');
            $productRepository->update([['id' => $productId, 'categories' => [['id' => $britishSalesChannel->getNavigationCategoryId()]]]], $context);
            $listingRoute = static::getContainer()->get(ProductListingRoute::class);
            self::assertInstanceOf(AbstractProductListingRoute::class, $listingRoute);
            $listingProduct = $listingRoute->load($britishSalesChannel->getNavigationCategoryId(), new Request(), $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()), new Criteria())->getResult()->get($productId);
            self::assertInstanceOf(ProductEntity::class, $listingProduct);
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $listingProduct->getDeliveryTimeId());

            $britishLink = $links->filterByProperty('salesChannelId', Market::UnitedKingdom->salesChannelId())->first();
            $deliveryTimeRepository->delete([['id' => $britishLink->getId()]], $context);
            $fallbackProduct = $detailRoute->load($productId, new Request(), $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()), new Criteria())->getProduct();
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $fallbackProduct->getDeliveryTimeId());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $this->storeApiDeliveryTimeId($productId, Market::UnitedKingdom));

            /** @var EntityRepository<ProductCollection> $productRepository */
            $productRepository = static::getContainer()->get('product.repository');
            $productRepository->create([[
                'id' => $variantProductId,
                'parentId' => $productId,
                'productNumber' => $productNumber.'-1',
                'stock' => 1,
            ]], $context);
            $variant = $detailRoute->load($variantProductId, new Request(), $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()), new Criteria())->getProduct();
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $variant->getDeliveryTimeId());
            $deliveryTimeRepository->create([[
                'id' => Uuid::randomHex(),
                'productId' => $variantProductId,
                'productVersionId' => Defaults::LIVE_VERSION,
                'salesChannelId' => Market::UnitedKingdom->salesChannelId(),
                'deliveryTimeId' => CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 3),
            ]], $context);
            $variantWithOverride = $detailRoute->load($variantProductId, new Request(), $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()), new Criteria())->getProduct();
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 3), $variantWithOverride->getDeliveryTimeId());
        } finally {
            /** @var EntityRepository<ProductCollection> $productRepository */
            $productRepository = static::getContainer()->get('product.repository');
            $productRepository->delete([['id' => $variantProductId], ['id' => $productId], ['id' => $crossSellingProductId]], $context);
        }
    }
}
