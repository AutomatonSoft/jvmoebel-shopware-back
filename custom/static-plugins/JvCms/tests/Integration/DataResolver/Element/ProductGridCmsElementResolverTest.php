<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ProductGridCalculatedPriceStruct;
use Jv\Cms\DataResolver\Element\ProductGridCmsElementResolver;
use Jv\Cms\DataResolver\Element\ProductGridCoverStruct;
use Jv\Cms\DataResolver\Element\ProductGridLinkStruct;
use Jv\Cms\DataResolver\Element\ProductGridListPriceStruct;
use Jv\Cms\DataResolver\Element\ProductGridMediaStruct;
use Jv\Cms\DataResolver\Element\ProductGridProductStruct;
use Jv\Cms\DataResolver\Element\ProductGridProductTranslatedStruct;
use Jv\Cms\DataResolver\Element\ProductGridStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ProductGridCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(ProductGridCmsElementResolver::class);
        self::assertInstanceOf(ProductGridCmsElementResolver::class, $resolver);
        self::assertSame('jv-product-grid', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'title' => '',
            'products' => [],
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->salesChannelContext(),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-product-grid', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertSame('cms_jv_product_grid', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertSame([], $data->getProducts());
        self::assertNull($data->getViewAll());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'eyebrow' => '  ',
            'products' => 'broken',
            'viewAll' => [
                'label' => 'View all',
                'url' => 'javascript:alert(1)',
            ],
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->salesChannelContext(),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_product_grid', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertSame('de-DE', $payload['locale']);
        self::assertSame('EUR', $payload['currency']);
        self::assertSame([], $payload['products']);
        self::assertNull($payload['viewAll']);
    }

    public function testStructEncoderSerializesNonEmptyProducts(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $sofaImage = new ProductGridMediaStruct('https://cdn.example.com/sofa.webp', 'Modular sofa');
        $chairImage = new ProductGridMediaStruct('https://cdn.example.com/chair.webp', 'Lounge chair');

        $data = new ProductGridStruct(
            title: 'Featured pieces',
            eyebrow: 'Selected for you',
            locale: 'de-DE',
            currency: 'EUR',
            products: [
                new ProductGridProductStruct(
                    id: 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                    position: 0,
                    url: '/product/noma',
                    translated: new ProductGridProductTranslatedStruct('Noma Lounge Chair', 'Rust bouclé'),
                    cover: new ProductGridCoverStruct($chairImage),
                    calculatedPrice: new ProductGridCalculatedPriceStruct(895.0, null),
                    badge: null,
                    ratingAverage: 4.8,
                    reviewCount: null,
                ),
                new ProductGridProductStruct(
                    id: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    position: 1,
                    url: '/product/alba',
                    translated: new ProductGridProductTranslatedStruct('Alba Modular Sofa', 'Natural bouclé'),
                    cover: new ProductGridCoverStruct($sofaImage),
                    calculatedPrice: new ProductGridCalculatedPriceStruct(
                        2490.0,
                        new ProductGridListPriceStruct(2890.0),
                    ),
                    badge: 'Bestseller',
                    ratingAverage: 4.9,
                    reviewCount: 128,
                ),
            ],
            viewAll: new ProductGridLinkStruct('View all products', '/shop'),
        );

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_product_grid', $payload['apiAlias']);
        self::assertSame('Featured pieces', $payload['title']);
        self::assertSame('Selected for you', $payload['eyebrow']);
        self::assertSame('de-DE', $payload['locale']);
        self::assertSame('EUR', $payload['currency']);
        self::assertCount(2, $payload['products']);
        self::assertSame('cms_jv_product_grid_product', $payload['products'][0]['apiAlias']);
        self::assertSame('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $payload['products'][0]['id']);
        self::assertSame('Noma Lounge Chair', $payload['products'][0]['translated']['name']);
        self::assertSame('https://cdn.example.com/chair.webp', $payload['products'][0]['cover']['media']['url']);
        self::assertSame(895.0, $payload['products'][0]['calculatedPrice']['unitPrice']);
        self::assertNull($payload['products'][0]['calculatedPrice']['listPrice']);
        self::assertSame('cms_jv_product_grid_product', $payload['products'][1]['apiAlias']);
        self::assertSame('Bestseller', $payload['products'][1]['badge']);
        self::assertSame(2890.0, $payload['products'][1]['calculatedPrice']['listPrice']['price']);
        self::assertSame(4.9, $payload['products'][1]['ratingAverage']);
        self::assertSame(128, $payload['products'][1]['reviewCount']);
        self::assertSame('cms_jv_product_grid_link', $payload['viewAll']['apiAlias']);
        self::assertSame('/shop', $payload['viewAll']['url']);
    }

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string|null,
     *     products?: mixed,
     *     viewAll?: mixed
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, $values['products'] ?? []));
        $config->add(new FieldConfig('viewAll', FieldConfig::SOURCE_STATIC, $values['viewAll'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-product-grid-integration');
        $slot->setType(ProductGridCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }

    private function salesChannelContext(): SalesChannelContext
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getLanguageInfo')->willReturn(new LanguageInfo('Deutsch', 'de-DE'));
        $context->method('getLanguageId')->willReturn('dddddddddddddddddddddddddddddddd');
        $context->method('getSalesChannelId')->willReturn('cccccccccccccccccccccccccccccccc');
        $context->method('getCurrency')->willReturn($currency);

        return $context;
    }
}
