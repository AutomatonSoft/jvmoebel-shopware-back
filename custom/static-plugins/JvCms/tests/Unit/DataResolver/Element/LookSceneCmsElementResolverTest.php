<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\LookSceneCmsElementResolver;
use Jv\Cms\DataResolver\Element\LookSceneStruct;
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

final class LookSceneCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string PRODUCT_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string PRODUCT_ID_2 = 'cccccccccccccccccccccccccccccccc';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new LookSceneCmsElementResolver();

        self::assertSame('jv-look-scene', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new LookSceneCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(LookSceneStruct::class, $data);
        self::assertSame('cms_jv_look_scene', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getDescription());
        self::assertNull($data->getImage());
        self::assertSame([], $data->getProducts());
        self::assertNull($data->getViewAll());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'title' => '  Living room scene  ',
            'description' => '  Curated pieces  ',
            'imageMedia' => self::MEDIA_ID,
            'products' => [
                [
                    'productId' => self::PRODUCT_ID,
                    'position' => 1,
                ],
                [
                    'id' => 'manual-chair',
                    'productId' => null,
                    'name' => '  Noma Lounge Chair  ',
                    'url' => '  /product/noma  ',
                    'position' => 0,
                ],
            ],
            'viewAll' => [
                'label' => '  View all  ',
                'url' => '  /living  ',
            ],
        ]);

        $result = $this->resultForSlot(
            $slot,
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/scene.webp', 'Living room'),
            [
                $this->product(self::PRODUCT_ID, 'Alba Modular Sofa'),
            ],
        );

        (new LookSceneCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(LookSceneStruct::class, $data);
        self::assertSame('Living room scene', $data->getTitle());
        self::assertSame('Curated pieces', $data->getDescription());

        $image = $data->getImage();
        self::assertNotNull($image);
        self::assertSame('https://cdn.example.com/scene.webp', $image->getUrl());
        self::assertSame('Living room', $image->getAlt());
        self::assertSame('cms_jv_look_scene_media', $image->getApiAlias());

        self::assertCount(2, $data->getProducts());

        $manual = $data->getProducts()[0];
        self::assertSame('manual-chair', $manual->getId());
        self::assertSame(0, $manual->getPosition());
        self::assertSame('Noma Lounge Chair', $manual->getName());
        self::assertSame('/product/noma', $manual->getUrl());

        $resolvedProduct = $data->getProducts()[1];
        self::assertSame(self::PRODUCT_ID, $resolvedProduct->getId());
        self::assertSame('Alba Modular Sofa', $resolvedProduct->getName());
        self::assertSame('/produkt/'.self::PRODUCT_ID, $resolvedProduct->getUrl());

        $viewAll = $data->getViewAll();
        self::assertNotNull($viewAll);
        self::assertSame('View all', $viewAll->getLabel());
        self::assertSame('/living', $viewAll->getUrl());
    }

    public function testCollectLoadsValidMediaAndDeduplicatedProducts(): void
    {
        $slot = $this->slot([
            'imageMedia' => self::MEDIA_ID,
            'products' => [
                ['productId' => self::PRODUCT_ID],
                ['productId' => self::PRODUCT_ID],
                ['productId' => self::PRODUCT_ID_2],
                ['productId' => 'not-a-uuid'],
            ],
        ]);

        $collection = (new LookSceneCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($collection);
        $all = $collection->all();
        self::assertSame(
            [self::MEDIA_ID],
            $all[MediaDefinition::class]['jv_look_scene_media_'.$slot->getUniqueIdentifier()]->getIds(),
        );

        $productIds = $all[ProductDefinition::class]['jv_look_scene_products_'.$slot->getUniqueIdentifier()]->getIds();
        sort($productIds);
        self::assertSame([self::PRODUCT_ID, self::PRODUCT_ID_2], $productIds);
    }

    public function testCollectIgnoresInvalidUuids(): void
    {
        $slot = $this->slot([
            'imageMedia' => 'broken',
            'products' => [['productId' => 'also-broken']],
        ]);

        self::assertNull((new LookSceneCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testDuplicateResolvedIdsAreSkipped(): void
    {
        $slot = $this->slot([
            'products' => [
                [
                    'id' => 'same',
                    'name' => 'First by position',
                    'url' => '/first',
                    'position' => 1,
                ],
                [
                    'id' => 'same',
                    'name' => 'Second by input',
                    'url' => '/second',
                    'position' => 2,
                ],
            ],
        ]);

        (new LookSceneCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(LookSceneStruct::class, $data);
        self::assertCount(1, $data->getProducts());
        self::assertSame('First by position', $data->getProducts()[0]->getName());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testManualProductAndViewAllRejectUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'products' => [[
                'name' => 'Manual',
                'url' => $url,
            ]],
            'viewAll' => [
                'label' => 'All',
                'url' => $url,
            ],
        ]);

        (new LookSceneCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(LookSceneStruct::class, $data);
        self::assertSame([], $data->getProducts());
        self::assertNull($data->getViewAll());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//example.com/product'];
        yield 'relative without slash' => ['product/sofa'];
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
                'jv_look_scene_media_'.$slot->getUniqueIdentifier(),
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
                'jv_look_scene_products_'.$slot->getUniqueIdentifier(),
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
        $media->setId($id);
        $media->setUrl($url);
        $media->setTranslated(['alt' => $alt]);

        return $media;
    }

    private function product(string $id, string $name): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setUniqueIdentifier($id);
        $product->setId($id);
        $product->setTranslated(['name' => $name]);

        return $product;
    }

    /**
     * @param array{
     *     title?: mixed,
     *     description?: mixed,
     *     imageMedia?: mixed,
     *     products?: mixed,
     *     viewAll?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $config->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, $values['products'] ?? []));
        $config->add(new FieldConfig('viewAll', FieldConfig::SOURCE_STATIC, $values['viewAll'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-look-scene');
        $slot->setType(LookSceneCmsElementResolver::TYPE);
        $slot->setFieldConfig($config);

        return $slot;
    }

    private function resolverContext(): ResolverContext
    {
        return new ResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
        );
    }
}
