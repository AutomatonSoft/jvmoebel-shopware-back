<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ProductGridCmsElementResolver;
use Jv\Cms\DataResolver\Element\ProductGridStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ProductGridCmsElementResolverTest extends TestCase
{
    private const string PRODUCT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string PRODUCT_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string SALES_CHANNEL_ID = 'cccccccccccccccccccccccccccccccc';
    private const string LANGUAGE_ID = 'dddddddddddddddddddddddddddddddd';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new ProductGridCmsElementResolver();

        self::assertSame('jv-product-grid', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidProductUuid(): void
    {
        $slot = $this->slot([
            'products' => [[
                'productId' => 'not-a-uuid',
            ]],
        ]);

        self::assertNull((new ProductGridCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidProductUuid(): void
    {
        $slot = $this->slot([
            'products' => [[
                'productId' => self::PRODUCT_ID,
            ]],
        ]);

        $criteriaCollection = (new ProductGridCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(ProductDefinition::class, $all);
        $named = $all[ProductDefinition::class];
        self::assertArrayHasKey('jv_product_grid_products_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::PRODUCT_ID], $named['jv_product_grid_products_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testCollectDedupesProductIds(): void
    {
        $slot = $this->slot([
            'products' => [
                ['productId' => self::PRODUCT_ID],
                ['productId' => self::PRODUCT_ID],
                ['productId' => self::PRODUCT_ID_2],
            ],
        ]);

        $criteriaCollection = (new ProductGridCmsElementResolver())->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $ids = $criteriaCollection->all()[ProductDefinition::class]['jv_product_grid_products_'.$slot->getUniqueIdentifier()]->getIds();
        sort($ids);

        self::assertSame([self::PRODUCT_ID, self::PRODUCT_ID_2], $ids);
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertSame('cms_jv_product_grid', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertSame('de-DE', $data->getLocale());
        self::assertSame('EUR', $data->getCurrency());
        self::assertSame([], $data->getProducts());
        self::assertNull($data->getViewAll());
    }

    public function testItNormalizesHappyPathWithTwoProducts(): void
    {
        $slot = $this->slot([
            'title' => '  Featured pieces  ',
            'eyebrow' => '  Selected for you  ',
            'products' => [
                [
                    'productId' => self::PRODUCT_ID,
                    'position' => 1,
                    'badge' => '  Bestseller  ',
                ],
                [
                    'productId' => self::PRODUCT_ID_2,
                    'position' => 0,
                    'badge' => '',
                ],
            ],
            'viewAll' => [
                'label' => '  View all  ',
                'url' => '  /shop  ',
            ],
        ]);

        $product1 = $this->product(
            self::PRODUCT_ID,
            'Alba Modular Sofa',
            '/product/alba',
            2490.0,
            2890.0,
            rating: 4.9,
        );
        $product2 = $this->product(
            self::PRODUCT_ID_2,
            'Noma Lounge Chair',
            '/product/noma',
            895.0,
        );

        $result = $this->resultForSlot($slot, [$product1, $product2]);
        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertSame('Featured pieces', $data->getTitle());
        self::assertSame('Selected for you', $data->getEyebrow());
        self::assertCount(2, $data->getProducts());

        self::assertSame(self::PRODUCT_ID_2, $data->getProducts()[0]->getId());
        self::assertSame(0, $data->getProducts()[0]->getPosition());
        self::assertNull($data->getProducts()[0]->getBadge());

        self::assertSame(self::PRODUCT_ID, $data->getProducts()[1]->getId());
        self::assertSame(1, $data->getProducts()[1]->getPosition());
        self::assertSame('Bestseller', $data->getProducts()[1]->getBadge());
        self::assertSame(2890.0, $data->getProducts()[1]->getCalculatedPrice()->getListPrice()?->getPrice());

        self::assertNotNull($data->getViewAll());
        self::assertSame('View all', $data->getViewAll()->getLabel());
        self::assertSame('/shop', $data->getViewAll()->getUrl());
    }

    public function testDuplicateProductIdsKeepFirstValidProduct(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'products' => [
                ['productId' => self::PRODUCT_ID, 'badge' => 'First'],
                ['productId' => self::PRODUCT_ID, 'badge' => 'Second'],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->product(self::PRODUCT_ID, 'First product', '/product/first', 100.0),
        ]);

        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertCount(1, $data->getProducts());
        self::assertSame('First', $data->getProducts()[0]->getBadge());
    }

    public function testPartialProductsAreSkipped(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'products' => [
                ['productId' => self::PRODUCT_ID],
                ['productId' => self::PRODUCT_ID_2],
            ],
        ]);

        $broken = $this->product(self::PRODUCT_ID, '', '/product/broken', 100.0);
        $valid = $this->product(self::PRODUCT_ID_2, 'Valid product', '/product/valid', 200.0);

        $result = $this->resultForSlot($slot, [$broken, $valid]);
        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertCount(1, $data->getProducts());
        self::assertSame('Valid product', $data->getProducts()[0]->getTranslated()->getName());
    }

    public function testMissingProductFromSearchResultOmitsSlot(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'products' => [[
                'productId' => self::PRODUCT_ID,
            ]],
        ]);

        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertSame([], $data->getProducts());
    }

    public function testInvalidProductUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'products' => [[
                'productId' => 'not-a-uuid',
            ]],
        ]);

        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertSame([], $data->getProducts());
    }

    public function testNonArrayProductsConfigYieldsEmptyProducts(): void
    {
        $slot = $this->slot(['products' => 'broken']);
        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertSame([], $data->getProducts());
    }

    public function testKeyedObjectConfigIsNormalizedToArray(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'products' => [
                'sofa' => [
                    'productId' => self::PRODUCT_ID,
                    'badge' => 'Bestseller',
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->product(self::PRODUCT_ID, 'Sofa', '/product/sofa', 2490.0),
        ]);
        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertCount(1, $data->getProducts());
        self::assertSame(self::PRODUCT_ID, $data->getProducts()[0]->getId());
        self::assertSame('Bestseller', $data->getProducts()[0]->getBadge());
    }

    public function testPreviousPriceIsNullWhenListPriceIsNotHigher(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'products' => [[
                'productId' => self::PRODUCT_ID,
            ]],
        ]);

        $product = $this->product(self::PRODUCT_ID, 'Product', '/product/p', 100.0, 100.0);
        $result = $this->resultForSlot($slot, [$product]);
        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertNull($data->getProducts()[0]->getCalculatedPrice()->getListPrice());
    }

    public function testPartialViewAllYieldsNull(): void
    {
        $slot = $this->slot([
            'viewAll' => [
                'label' => 'View all',
                'url' => '',
            ],
        ]);

        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertNull($data->getViewAll());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'eyebrow' => '',
            'products' => [[
                'productId' => self::PRODUCT_ID,
                'badge' => 'Sale',
            ]],
            'viewAll' => [
                'label' => 'View all',
                'url' => '/shop',
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->product(self::PRODUCT_ID, 'Product', '/product/p', 100.0, 120.0),
        ]);
        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_product_grid', $payload['apiAlias']);
        self::assertSame('Title', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertSame('de-DE', $payload['locale']);
        self::assertSame('EUR', $payload['currency']);
        self::assertIsArray($payload['products']);
        self::assertSame('cms_jv_product_grid_product', $payload['products'][0]['apiAlias']);
        self::assertSame('Product', $payload['products'][0]['translated']['name']);
        self::assertSame('/product/p', $payload['products'][0]['url']);
        self::assertSame('https://cdn.example.com/product.webp', $payload['products'][0]['cover']['media']['url']);
        self::assertSame(100.0, $payload['products'][0]['calculatedPrice']['unitPrice']);
        self::assertSame(120.0, $payload['products'][0]['calculatedPrice']['listPrice']['price']);
        self::assertSame('Sale', $payload['products'][0]['badge']);
        self::assertSame('cms_jv_product_grid_link', $payload['viewAll']['apiAlias']);
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsRelativeAndAbsoluteViewAllUrls(string $url, string $expected): void
    {
        $slot = $this->slot([
            'viewAll' => [
                'label' => 'View all',
                'url' => $url,
            ],
        ]);

        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertNotNull($data->getViewAll());
        self::assertSame($expected, $data->getViewAll()->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'relative' => ['/shop', '/shop'];
        yield 'query string' => ['/shop?q=1', '/shop?q=1'];
        yield 'https' => ['https://example.com/path', 'https://example.com/path'];
        yield 'trimmed relative' => ['  /shop  ', '/shop'];
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeViewAllUrls(string $url): void
    {
        $slot = $this->slot([
            'viewAll' => [
                'label' => 'View all',
                'url' => $url,
            ],
        ]);

        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertNull($data->getViewAll());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['shop'];
        yield 'https without host' => ['https://'];
    }

    public function testProductWithoutSeoUrlForCurrentChannelIsSkipped(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'products' => [[
                'productId' => self::PRODUCT_ID,
            ]],
        ]);

        $product = $this->product(self::PRODUCT_ID, 'Product', '/product/p', 100.0);
        $seoUrl = new SeoUrlEntity();
        $seoUrl->setUniqueIdentifier(Uuid::randomHex());
        $seoUrl->setSalesChannelId('other-sales-channel');
        $seoUrl->setLanguageId(self::LANGUAGE_ID);
        $seoUrl->setSeoPathInfo('product/p');
        $seoUrl->setIsCanonical(true);
        $product->setSeoUrls(new SeoUrlCollection([$seoUrl]));

        $result = $this->resultForSlot($slot, [$product]);
        (new ProductGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ProductGridStruct::class, $data);
        self::assertSame([], $data->getProducts());
    }

    /**
     * @param list<SalesChannelProductEntity> $products
     */
    private function resultForSlot(CmsSlotEntity $slot, array $products): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_product_grid_products_'.$slot->getUniqueIdentifier(),
            new EntitySearchResult(
                ProductDefinition::ENTITY_NAME,
                \count($products),
                new ProductCollection($products),
                null,
                new Criteria(array_map(static fn (SalesChannelProductEntity $product): string => $product->getUniqueIdentifier(), $products)),
                Context::createDefaultContext(),
            ),
        );

        return $result;
    }

    private function product(
        string $id,
        string $name,
        string $path,
        float $unitPrice,
        ?float $listPrice = null,
        string $imageUrl = 'https://cdn.example.com/product.webp',
        string $imageAlt = 'Product image',
        ?float $rating = null,
    ): SalesChannelProductEntity {
        $media = new MediaEntity();
        $media->setUniqueIdentifier(Uuid::randomHex());
        $media->setUrl($imageUrl);
        $media->setTranslated(['alt' => $imageAlt]);

        $cover = new ProductMediaEntity();
        $cover->setUniqueIdentifier(Uuid::randomHex());
        $cover->setMedia($media);

        $calculatedPrice = new CalculatedPrice(
            $unitPrice,
            $unitPrice,
            new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(),
            new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection(),
            1,
            null,
            null !== $listPrice ? ListPrice::createFromUnitPrice($unitPrice, $listPrice) : null,
        );

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setUniqueIdentifier(Uuid::randomHex());
        $seoUrl->setSalesChannelId(self::SALES_CHANNEL_ID);
        $seoUrl->setLanguageId(self::LANGUAGE_ID);
        $seoUrl->setSeoPathInfo(ltrim($path, '/'));
        $seoUrl->setIsCanonical(true);

        $product = new SalesChannelProductEntity();
        $product->setUniqueIdentifier($id);
        $product->setId($id);
        $product->setTranslated(['name' => $name]);
        $product->setCover($cover);
        $product->setCalculatedPrice($calculatedPrice);
        $product->setSeoUrls(new SeoUrlCollection([$seoUrl]));
        if (null !== $rating) {
            $product->setRatingAverage($rating);
        }

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function storeApiArray(Struct $struct): array
    {
        $payload = $struct->jsonSerialize();
        foreach ($payload as $key => $value) {
            if ($value instanceof Struct) {
                $payload[$key] = $this->storeApiArray($value);
            } elseif (\is_array($value)) {
                $payload[$key] = $this->storeApiList($value);
            }
        }

        $payload['apiAlias'] = $struct->getApiAlias();
        if (isset($payload['extensions']) && [] === $payload['extensions']) {
            unset($payload['extensions']);
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function storeApiList(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof Struct) {
                $values[$key] = $this->storeApiArray($value);
            } elseif (\is_array($value)) {
                $values[$key] = $this->storeApiList($value);
            }
        }

        return $values;
    }

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string,
     *     products?: mixed,
     *     viewAll?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, $values['products'] ?? []));
        $collection->add(new FieldConfig('viewAll', FieldConfig::SOURCE_STATIC, $values['viewAll'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-product-grid');
        $slot->setType(ProductGridCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        return $slot;
    }

    private function resolverContext(): ResolverContext
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getLanguageInfo')->willReturn(new LanguageInfo('Deutsch', 'de-DE'));
        $context->method('getLanguageId')->willReturn(self::LANGUAGE_ID);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);
        $context->method('getCurrency')->willReturn($currency);

        return new ResolverContext($context, new Request());
    }
}
