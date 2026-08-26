<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Integration\CosmoShop\CosmoShopProductIdentity;
use Jv\Import\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupData;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupItemData;
use Jv\Import\Service\ProductImport\LookupData\UpsertProductImportLookupDataService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
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
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Symfony\Component\HttpFoundation\Request;

final class CosmoShopMarketDeliveryTimeCmsTest extends AbstractCosmoShopImportExportTestCase
{
    public function testItUsesTheMarketDeliveryTimeForCmsProductSlider(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'MARKET-DELIVERY-CMS-001';
        $productId = CosmoShopProductIdentity::fromProductNumber($productNumber);
        $references = static::getContainer()->get(UpsertProductImportLookupDataService::class);
        self::assertInstanceOf(UpsertProductImportLookupDataService::class, $references);
        $profileId = $this->configureMarketProfile(Market::UnitedKingdom, $context);
        $references->execute(Market::UnitedKingdom, new ProductImportLookupData([new ProductImportLookupItemData('2', ['en' => 'Delivery time: 6-10 weeks'])], []), $context);

        try {
            self::assertSame(Progress::STATE_SUCCEEDED, $this->import($profileId, $this->csv(productNumber: $productNumber, deliveryTimeId: '2', urlKey: 'market-delivery-cms-001'))->getState());
            $contextFactory = static::getContainer()->get(CachedSalesChannelContextFactory::class);
            self::assertInstanceOf(AbstractSalesChannelContextFactory::class, $contextFactory);
            $detailRoute = static::getContainer()->get(ProductDetailRoute::class);
            self::assertInstanceOf(AbstractProductDetailRoute::class, $detailRoute);
            $product = $detailRoute->load($productId, new Request(), $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId()), new Criteria())->getProduct();
            $product->setDeliveryTimeId(null);

            $slider = new ProductSliderStruct();
            $slider->setProducts(new ProductCollection([$product]));
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

            static::getContainer()->get('event_dispatcher')->dispatch(new CmsPageLoadedEvent(new Request(), new CmsPageCollection([$page]), $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId())));
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, 2), $product->getDeliveryTimeId());
        } finally {
            /** @var EntityRepository<ProductCollection> $productRepository */
            $productRepository = static::getContainer()->get('product.repository');
            $productRepository->delete([['id' => $productId]], $context);
        }
    }
}
