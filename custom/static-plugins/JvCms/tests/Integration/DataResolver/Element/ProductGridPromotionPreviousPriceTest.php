<?php declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ProductGridCmsElementResolver;
use Jv\Cms\DataResolver\Element\ProductGridStruct;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Jv\Promotion\JvPromotionConstants;
use Jv\Promotion\Service\Write\SyncJvPromotionService;
use Jv\Promotion\Tests\Integration\Support\MarketSalesChannelTestTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;

final class ProductGridPromotionPreviousPriceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use MarketSalesChannelTestTrait;

    public function testProductGridUsesPromotionBaseAsPreviousPrice(): void
    {
        $context = Context::createDefaultContext();
        $this->ensureMarketSalesChannel(Market::Germany, $context);

        $ids = new IdsCollection();
        $productId = $ids->create('grid-promo-product');
        $sourceId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        (new ProductBuilder($ids, 'grid-promo-product'))
            ->name('Product grid promotion previous price test')
            ->price(3539.0, null, 'default', 4200.0)
            ->visibility(Market::Germany->salesChannelId(), ProductVisibilityDefinition::VISIBILITY_ALL)
            ->cover('grid-promo-cover')
            ->write(static::getContainer());

        $cover = static::getContainer()->get('product_media.repository')->search(
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$ids->get('grid-promo-cover')]),
            $context,
        )->first();
        self::assertNotNull($cover);
        static::getContainer()->get('media.repository')->update([[
            'id' => $cover->getMediaId(),
            'path' => 'media/grid-promo-cover.jpg',
            'mimeType' => 'image/jpeg',
            'fileExtension' => 'jpg',
        ]], $context);

        static::getContainer()->get('seo_url.repository')->create([[
            'id' => Uuid::randomHex(),
            'languageId' => Market::Germany->languageId(),
            'salesChannelId' => Market::Germany->salesChannelId(),
            'foreignKey' => $productId,
            'routeName' => 'frontend.detail.page',
            'pathInfo' => '/detail/'.$productId,
            'seoPathInfo' => 'grid-promo-product',
            'isCanonical' => true,
            'isDeleted' => false,
        ]], $context);

        static::getContainer()->get('jv_aftercool_product_source.repository')->create([[
            'id' => $sourceId,
            'account' => 'JV',
            'dataset' => 'lister',
            'factoryId' => 498371,
            'factoryName' => 'UK-GANASI',
            'sourceProductId' => '900003',
            'productId' => $productId,
            'sourceArtikelnummer' => '900003',
            'sourceEan' => '4260174422192',
            'stammartikelId' => '175220799',
            'collectionName' => 'Sofa L6004B',
            'sourceFilePrefix' => 'UK-GANASI',
            'lastSeenAt' => new \DateTimeImmutable(),
        ]], $context);

        try {
            static::getContainer()->get(SyncJvPromotionService::class)->execute([
                'promotionId' => $promotionId,
                'name' => 'Integration grid promo 20%',
                'active' => true,
                'discountPercent' => 20.0,
                'targets' => [
                    ['type' => 'product', 'ean' => '4260174422192'],
                ],
            ], $context);

            /** @var SalesChannelContextFactory $contextFactory */
            $contextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
            $salesChannelContext = $contextFactory->create(Uuid::randomHex(), Market::Germany->salesChannelId());

            $product = static::getContainer()->get('sales_channel.product.repository')->search(
                (new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$productId]))
                    ->addAssociation('cover.media')
                    ->addAssociation('seoUrls'),
                $salesChannelContext,
            )->first();
            self::assertInstanceOf(SalesChannelProductEntity::class, $product);
            self::assertNotNull($product->getExtension(JvPromotionConstants::EXTENSION_BASE_PRICE));

            $slot = $this->createSlot($productId);
            /** @var CmsSlotsDataResolver $slotsResolver */
            $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
            $resolved = $slotsResolver->resolve(
                new CmsSlotCollection([$slot]),
                new ResolverContext($salesChannelContext, new Request()),
            );

            $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
            self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
            $data = $resolvedSlot->getData();
            self::assertInstanceOf(ProductGridStruct::class, $data);
            self::assertSame(3539.0, $data->getProducts()[0]->getCalculatedPrice()->getListPrice()?->getPrice());
            self::assertSame(2831.2, $data->getProducts()[0]->getCalculatedPrice()->getUnitPrice());
        } finally {
            static::getContainer()->get('promotion.repository')->delete([['id' => $promotionId]], $context);
            static::getContainer()->get('jv_aftercool_product_source.repository')->delete([['id' => $sourceId]], $context);
            static::getContainer()->get('product.repository')->delete([['id' => $productId]], $context);
        }
    }

    private function createSlot(string $productId): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, 'Promo grid'));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, ''));
        $config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, [[
            'productId' => $productId,
        ]]));
        $config->add(new FieldConfig('viewAll', FieldConfig::SOURCE_STATIC, [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-product-grid-promo');
        $slot->setType(ProductGridCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
