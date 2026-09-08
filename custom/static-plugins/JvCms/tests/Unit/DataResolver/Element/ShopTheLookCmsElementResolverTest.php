<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ShopTheLookCmsElementResolver;
use Jv\Cms\DataResolver\Element\ShopTheLookStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ShopTheLookCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string PRODUCT_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string PRODUCT_ID_2 = 'cccccccccccccccccccccccccccccccc';

    public function testItExposesTypeAndEmptyCollectIsNull(): void
    {
        $resolver = new ShopTheLookCmsElementResolver();

        self::assertSame('jv-shop-the-look', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectLoadsValidMediaAndDeduplicatedProducts(): void
    {
        $slot = $this->slot([
            'imageMedia' => self::MEDIA_ID,
            'items' => [
                ['productId' => self::PRODUCT_ID],
                ['productId' => self::PRODUCT_ID],
                ['productId' => self::PRODUCT_ID_2],
                ['productId' => 'not-a-uuid'],
            ],
        ]);

        $collection = (new ShopTheLookCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($collection);
        $all = $collection->all();
        self::assertSame(
            [self::MEDIA_ID],
            $all[MediaDefinition::class]['jv_shop_the_look_media_'.$slot->getUniqueIdentifier()]->getIds(),
        );
        $productIds = $all[ProductDefinition::class]['jv_shop_the_look_products_'.$slot->getUniqueIdentifier()]->getIds();
        sort($productIds);
        self::assertSame([self::PRODUCT_ID, self::PRODUCT_ID_2], $productIds);
    }

    public function testCollectIgnoresInvalidUuids(): void
    {
        $slot = $this->slot([
            'imageMedia' => 'broken',
            'items' => [['productId' => 'also-broken']],
        ]);

        self::assertNull((new ShopTheLookCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new ShopTheLookCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ShopTheLookStruct::class, $data);
        self::assertSame('cms_jv_shop_the_look', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertNull($data->getImage());
        self::assertSame([], $data->getItems());
        self::assertNull($data->getViewAll());
    }

    public function testItResolvesImageAndProductAndManualItems(): void
    {
        $slot = $this->slot([
            'title' => '  Bring this look home.  ',
            'eyebrow' => '  One room, one look  ',
            'description' => '  Selected furniture.  ',
            'imageMedia' => self::MEDIA_ID,
            'items' => [
                [
                    'productId' => self::PRODUCT_ID,
                    'description' => '  Editorial override  ',
                    'hotspot' => ['x' => 69, 'y' => 66.5],
                    'position' => 1,
                ],
                [
                    'id' => 'manual-chair',
                    'productId' => null,
                    'name' => '  Noma Lounge Chair  ',
                    'description' => '  Rust bouclé  ',
                    'url' => '  /product/noma  ',
                    'hotspot' => ['x' => 28.5, 'y' => 72],
                    'position' => 0,
                ],
            ],
            'viewAll' => ['label' => '  View all  ', 'url' => '  /living  '],
        ]);
        $product = $this->product(self::PRODUCT_ID, 'Alba Modular Sofa', 'Product description');
        $result = $this->resultForSlot(
            $slot,
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/look.webp', 'Living room'),
            [$product],
        );

        (new ShopTheLookCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ShopTheLookStruct::class, $data);
        self::assertSame('Bring this look home.', $data->getTitle());
        self::assertSame('One room, one look', $data->getEyebrow());
        self::assertSame('Selected furniture.', $data->getDescription());
        $image = $data->getImage();
        self::assertNotNull($image);
        self::assertSame('https://cdn.example.com/look.webp', $image->getUrl());
        self::assertSame('Living room', $image->getAlt());
        self::assertCount(2, $data->getItems());

        $manual = $data->getItems()[0];
        self::assertSame('manual-chair', $manual->getId());
        self::assertSame('Noma Lounge Chair', $manual->getName());
        self::assertSame('Rust bouclé', $manual->getDescription());
        self::assertSame('/product/noma', $manual->getUrl());
        self::assertSame(['x' => 28.5, 'y' => 72.0], $manual->getHotspot());

        $resolvedProduct = $data->getItems()[1];
        self::assertSame(self::PRODUCT_ID, $resolvedProduct->getId());
        self::assertSame('Alba Modular Sofa', $resolvedProduct->getName());
        self::assertSame('Editorial override', $resolvedProduct->getDescription());
        self::assertSame('/produkt/'.self::PRODUCT_ID, $resolvedProduct->getUrl());
        self::assertSame(['x' => 69.0, 'y' => 66.5], $resolvedProduct->getHotspot());
        $viewAll = $data->getViewAll();
        self::assertNotNull($viewAll);
        self::assertSame('View all', $viewAll->getLabel());
        self::assertSame('/living', $viewAll->getUrl());
    }

    public function testProductDescriptionFallsBackToTranslatedValue(): void
    {
        $slot = $this->slot([
            'items' => [[
                'productId' => self::PRODUCT_ID,
                'description' => ' ',
                'hotspot' => ['x' => 50, 'y' => 50],
            ]],
        ]);
        $result = $this->resultForSlot(
            $slot,
            null,
            [$this->product(self::PRODUCT_ID, 'Sofa', 'Translated description')],
        );

        (new ShopTheLookCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ShopTheLookStruct::class, $data);
        self::assertSame('Translated description', $data->getItems()[0]->getDescription());
    }

    public function testKeyedItemsAreSortedAndDuplicateResolvedIdsAreSkipped(): void
    {
        $slot = $this->slot([
            'items' => [
                'second' => [
                    'id' => 'same',
                    'name' => 'Second by input',
                    'url' => '/second',
                    'hotspot' => ['x' => 20, 'y' => 20],
                    'position' => 2,
                ],
                'first' => [
                    'id' => 'same',
                    'name' => 'First by position',
                    'url' => '/first',
                    'hotspot' => ['x' => 10, 'y' => 10],
                    'position' => 1,
                ],
            ],
        ]);

        (new ShopTheLookCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ShopTheLookStruct::class, $data);
        self::assertCount(1, $data->getItems());
        self::assertSame('First by position', $data->getItems()[0]->getName());
    }

    #[DataProvider('invalidHotspotProvider')]
    public function testInvalidHotspotOmitsItem(mixed $hotspot): void
    {
        $slot = $this->slot([
            'items' => [[
                'name' => 'Manual',
                'url' => '/manual',
                'hotspot' => $hotspot,
            ]],
        ]);

        (new ShopTheLookCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ShopTheLookStruct::class, $data);
        self::assertSame([], $data->getItems());
    }

    /** @return iterable<string, array{0: mixed}> */
    public static function invalidHotspotProvider(): iterable
    {
        yield 'not an object' => ['broken'];
        yield 'missing y' => [['x' => 50]];
        yield 'x below zero' => [['x' => -0.1, 'y' => 50]];
        yield 'x above hundred' => [['x' => 100.1, 'y' => 50]];
        yield 'numeric strings rejected' => [['x' => '50', 'y' => 50]];
        yield 'not finite' => [['x' => \NAN, 'y' => 50]];
    }

    public function testHotspotBoundariesAreAccepted(): void
    {
        $slot = $this->slot([
            'items' => [[
                'name' => 'Manual',
                'url' => '/manual',
                'hotspot' => ['x' => 0, 'y' => 100],
            ]],
        ]);

        (new ShopTheLookCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ShopTheLookStruct::class, $data);
        self::assertSame(['x' => 0.0, 'y' => 100.0], $data->getItems()[0]->getHotspot());
    }

    public function testInvalidProductReferenceDoesNotFallBackToManualFields(): void
    {
        $slot = $this->slot([
            'items' => [[
                'productId' => 'invalid',
                'name' => 'Manual fallback must not be used',
                'url' => '/manual',
                'hotspot' => ['x' => 50, 'y' => 50],
            ]],
        ]);

        (new ShopTheLookCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ShopTheLookStruct::class, $data);
        self::assertSame([], $data->getItems());
    }

    #[DataProvider('safeHrefProvider')]
    public function testManualAndViewAllLinksAcceptSafeUrls(string $url): void
    {
        $slot = $this->slot([
            'items' => [[
                'name' => 'Manual',
                'url' => $url,
                'hotspot' => ['x' => 50, 'y' => 50],
            ]],
            'viewAll' => ['label' => 'All', 'url' => $url],
        ]);

        (new ShopTheLookCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ShopTheLookStruct::class, $data);
        self::assertCount(1, $data->getItems());
        self::assertNotNull($data->getViewAll());
    }

    /** @return iterable<string, array{0: string}> */
    public static function safeHrefProvider(): iterable
    {
        yield 'root relative' => ['/product/sofa'];
        yield 'absolute https' => ['https://example.com/product/sofa'];
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testManualAndViewAllLinksRejectUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'items' => [[
                'name' => 'Manual',
                'url' => $url,
                'hotspot' => ['x' => 50, 'y' => 50],
            ]],
            'viewAll' => ['label' => 'All', 'url' => $url],
        ]);

        (new ShopTheLookCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ShopTheLookStruct::class, $data);
        self::assertSame([], $data->getItems());
        self::assertNull($data->getViewAll());
    }

    /** @return iterable<string, array{0: string}> */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//example.com/product'];
        yield 'relative without slash' => ['product/sofa'];
    }

    public function testProductUsesFrontendIdRoute(): void
    {
        $slot = $this->slot([
            'items' => [[
                'productId' => self::PRODUCT_ID,
                'hotspot' => ['x' => 50, 'y' => 50],
            ]],
        ]);
        $product = $this->product(self::PRODUCT_ID, 'Sofa');
        $result = $this->resultForSlot($slot, null, [$product]);

        (new ShopTheLookCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ShopTheLookStruct::class, $data);
        self::assertCount(1, $data->getItems());
        self::assertSame('/produkt/'.self::PRODUCT_ID, $data->getItems()[0]->getUrl());
    }

    /**
     * @param list<SalesChannelProductEntity> $products
     */
    private function resultForSlot(
        CmsSlotEntity $slot,
        ?MediaEntity $media,
        array $products,
    ): ElementDataCollection {
        $result = new ElementDataCollection();

        if (null !== $media) {
            $result->add(
                'jv_shop_the_look_media_'.$slot->getUniqueIdentifier(),
                new EntitySearchResult(
                    MediaDefinition::ENTITY_NAME,
                    1,
                    new MediaCollection([$media]),
                    null,
                    new Criteria([$media->getUniqueIdentifier()]),
                    Context::createDefaultContext(),
                ),
            );
        }

        if ([] !== $products) {
            $result->add(
                'jv_shop_the_look_products_'.$slot->getUniqueIdentifier(),
                new EntitySearchResult(
                    ProductDefinition::ENTITY_NAME,
                    \count($products),
                    new ProductCollection($products),
                    null,
                    new Criteria(array_map(
                        static fn (SalesChannelProductEntity $product): string => $product->getUniqueIdentifier(),
                        $products,
                    )),
                    Context::createDefaultContext(),
                ),
            );
        }

        return $result;
    }

    private function media(string $id, string $url, string $alt): MediaEntity
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier($id);
        $media->setUrl($url);
        $media->setTranslated(['alt' => $alt]);

        return $media;
    }

    private function product(
        string $id,
        string $name,
        ?string $description = null,
    ): SalesChannelProductEntity {
        $product = new SalesChannelProductEntity();
        $product->setUniqueIdentifier($id);
        $product->setId($id);
        $product->setTranslated(['name' => $name, 'description' => $description]);

        return $product;
    }

    /**
     * @param array{
     *     title?: mixed,
     *     eyebrow?: mixed,
     *     description?: mixed,
     *     imageMedia?: mixed,
     *     items?: mixed,
     *     viewAll?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $config->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $config->add(new FieldConfig('items', FieldConfig::SOURCE_STATIC, $values['items'] ?? []));
        $config->add(new FieldConfig('viewAll', FieldConfig::SOURCE_STATIC, $values['viewAll'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-shop-the-look');
        $slot->setType(ShopTheLookCmsElementResolver::TYPE);
        $slot->setFieldConfig($config);

        return $slot;
    }

    private function resolverContext(): ResolverContext
    {
        $context = $this->createMock(SalesChannelContext::class);

        return new ResolverContext($context, new Request());
    }
}
