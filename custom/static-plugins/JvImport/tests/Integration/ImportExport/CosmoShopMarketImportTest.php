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
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
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

final class CosmoShopMarketImportTest extends AbstractCosmoShopImportExportTestCase
{
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

            self::assertNull($germanProduct->getExtension('jvImportDeliveryTimes'));
            self::assertNull($britishProduct->getExtension('jvImportDeliveryTimes'));
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $germanProduct->getDeliveryTimeId());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $britishProduct->getDeliveryTimeId());
            $britishProduct->setDeliveryTimeId(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2));
            $slider = new ProductSliderStruct();
            $slider->setProducts(new ProductCollection([$britishProduct]));
            $slot = new CmsSlotEntity();
            $slot->setId(Uuid::randomHex());
            $slot->setSlot('content');
            $slot->setType('product-slider');
            $slot->setData($slider);
            $block = new CmsBlockEntity();
            $block->setId(Uuid::randomHex());
            $block->setSlots(new CmsSlotCollection([$slot]));
            $section = new CmsSectionEntity();
            $section->setId(Uuid::randomHex());
            $section->setBlocks(new CmsBlockCollection([$block]));
            $page = new CmsPageEntity();
            $page->setId(Uuid::randomHex());
            $page->setSections(new CmsSectionCollection([$section]));
            $britishContext = $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId());
            static::getContainer()->get('event_dispatcher')->dispatch(new CmsPageLoadedEvent(new Request(), new CmsPageCollection([$page]), $britishContext));
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

            $delivery = $britishCart->getDeliveries()->first();
            self::assertNotNull($delivery);
            $address = new CustomerAddressEntity();
            $address->setId(Uuid::randomHex());
            $address->setCountryId($britishCartContext->getShippingLocation()->getCountry()->getId());
            $address->setCountry($britishCartContext->getShippingLocation()->getCountry());
            $address->setFirstName('Test');
            $address->setLastName('Customer');
            $address->setStreet('Test street 1');
            $address->setCity('Test city');
            $britishCart->setDeliveries(new DeliveryCollection([new Delivery(
                $delivery->getPositions(),
                $delivery->getDeliveryDate(),
                $delivery->getShippingMethod(),
                ShippingLocation::createFromAddress($address),
                $delivery->getShippingCosts(),
            )]));
            $orderConverter = static::getContainer()->get(OrderConverter::class);
            self::assertInstanceOf(OrderConverter::class, $orderConverter);
            $orderData = $orderConverter->convertToOrder(
                $britishCart,
                $britishCartContext,
                (new OrderConversionContext())
                    ->setIncludeCustomer(false)
                    ->setIncludeBillingAddress(false)
                    ->setIncludeTransactions(false),
            );
            self::assertSame(
                $britishCart->getDeliveries()->first()?->getDeliveryDate()->getEarliest()->format('Y-m-d H:i:s.v'),
                $orderData['deliveries'][0]['shippingDateEarliest'] ?? null,
            );

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
}
