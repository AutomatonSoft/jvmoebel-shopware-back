<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\InlineProductTeaserCmsElementResolver;
use Jv\Cms\DataResolver\Element\InlineProductTeaserStruct;
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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class InlineProductTeaserCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string PRODUCT_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string SALES_CHANNEL_ID = 'cccccccccccccccccccccccccccccccc';
    private const string LANGUAGE_ID = 'dddddddddddddddddddddddddddddddd';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new InlineProductTeaserCmsElementResolver();

        self::assertSame('jv-inline-product-teaser', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectAddsProductCriteriaWhenProductIdIsValid(): void
    {
        $slot = $this->slot(['productId' => self::PRODUCT_ID]);

        $collection = (new InlineProductTeaserCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($collection);
        $all = $collection->all();
        self::assertArrayHasKey(ProductDefinition::class, $all);
        self::assertSame(
            [self::PRODUCT_ID],
            $all[ProductDefinition::class]['jv_inline_product_teaser_product_'.$slot->getUniqueIdentifier()]->getIds(),
        );
    }

    public function testCollectAddsMediaCriteriaWhenImageMediaIsValid(): void
    {
        $slot = $this->slot(['imageMedia' => self::MEDIA_ID]);

        $collection = (new InlineProductTeaserCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($collection);
        $all = $collection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        self::assertSame(
            [self::MEDIA_ID],
            $all[MediaDefinition::class]['jv_inline_product_teaser_media_'.$slot->getUniqueIdentifier()]->getIds(),
        );
    }

    public function testCollectLoadsBothProductAndMediaWhenBothAreValid(): void
    {
        $slot = $this->slot([
            'productId' => self::PRODUCT_ID,
            'imageMedia' => self::MEDIA_ID,
        ]);

        $collection = (new InlineProductTeaserCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($collection);
        $all = $collection->all();
        self::assertArrayHasKey(ProductDefinition::class, $all);
        self::assertArrayHasKey(MediaDefinition::class, $all);
    }

    public function testCollectIgnoresInvalidUuids(): void
    {
        $slot = $this->slot([
            'productId' => 'not-a-uuid',
            'imageMedia' => 'also-broken',
        ]);

        self::assertNull((new InlineProductTeaserCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new InlineProductTeaserCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(InlineProductTeaserStruct::class, $data);
        self::assertSame('cms_jv_inline_product_teaser', $data->getApiAlias());
        self::assertNull($data->getProductId());
        self::assertSame('', $data->getName());
        self::assertNull($data->getDescription());
        self::assertNull($data->getImage());
        self::assertNull($data->getLink());
    }

    public function testProductModeResolvesNameUrlAndCoverImage(): void
    {
        $slot = $this->slot([
            'productId' => self::PRODUCT_ID,
            'description' => '  Editorial note  ',
        ]);

        $product = $this->product(
            self::PRODUCT_ID,
            'Alba Modular Sofa',
            'produkt/alba-modular-sofa',
        );

        $result = $this->resultForSlot($slot, $product, null);
        (new InlineProductTeaserCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(InlineProductTeaserStruct::class, $data);
        self::assertSame(self::PRODUCT_ID, $data->getProductId());
        self::assertSame('Alba Modular Sofa', $data->getName());
        self::assertSame('Editorial note', $data->getDescription());

        $image = $data->getImage();
        self::assertNotNull($image);
        self::assertSame('cms_jv_inline_product_teaser_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/product.webp', $image->getUrl());
        self::assertSame('Product image', $image->getAlt());

        $link = $data->getLink();
        self::assertNotNull($link);
        self::assertSame('cms_jv_inline_product_teaser_link', $link->getApiAlias());
        self::assertSame('Alba Modular Sofa', $link->getLabel());
        self::assertSame('/produkt/alba-modular-sofa', $link->getUrl());
    }

    public function testProductModeFallsBackToConfigImageWhenCoverIsMissing(): void
    {
        $slot = $this->slot([
            'productId' => self::PRODUCT_ID,
            'imageMedia' => self::MEDIA_ID,
        ]);

        $product = $this->product(self::PRODUCT_ID, 'Sofa', 'produkt/sofa', withCover: false);
        $media = $this->media(self::MEDIA_ID, 'https://cdn.example.com/fallback.webp', 'Fallback image');

        $result = $this->resultForSlot($slot, $product, $media);
        (new InlineProductTeaserCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(InlineProductTeaserStruct::class, $data);
        self::assertNotNull($data->getImage());
        self::assertSame('https://cdn.example.com/fallback.webp', $data->getImage()->getUrl());
        self::assertSame('Fallback image', $data->getImage()->getAlt());
    }

    public function testMissingProductFallsBackToManualFields(): void
    {
        $slot = $this->slot([
            'productId' => self::PRODUCT_ID,
            'name' => '  Manual Sofa  ',
            'url' => '  /product/manual  ',
            'description' => '  Manual description  ',
            'imageMedia' => self::MEDIA_ID,
        ]);

        $media = $this->media(self::MEDIA_ID, 'https://cdn.example.com/manual.webp', 'Manual image');
        $result = $this->resultForSlot($slot, null, $media);

        (new InlineProductTeaserCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(InlineProductTeaserStruct::class, $data);
        self::assertNull($data->getProductId());
        self::assertSame('Manual Sofa', $data->getName());
        self::assertSame('Manual description', $data->getDescription());
        self::assertSame('https://cdn.example.com/manual.webp', $data->getImage()?->getUrl());

        $link = $data->getLink();
        self::assertNotNull($link);
        self::assertSame('Manual Sofa', $link->getLabel());
        self::assertSame('/product/manual', $link->getUrl());
    }

    public function testManualModeResolvesConfiguredFields(): void
    {
        $slot = $this->slot([
            'name' => '  Noma Lounge Chair  ',
            'url' => '  /product/noma  ',
            'description' => '  Rust bouclé  ',
            'imageMedia' => self::MEDIA_ID,
        ]);

        $media = $this->media(self::MEDIA_ID, 'https://cdn.example.com/noma.webp', 'Noma chair');
        $result = $this->resultForSlot($slot, null, $media);

        (new InlineProductTeaserCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(InlineProductTeaserStruct::class, $data);
        self::assertNull($data->getProductId());
        self::assertSame('Noma Lounge Chair', $data->getName());
        self::assertSame('Rust bouclé', $data->getDescription());
        self::assertSame('https://cdn.example.com/noma.webp', $data->getImage()?->getUrl());

        $link = $data->getLink();
        self::assertNotNull($link);
        self::assertSame('Noma Lounge Chair', $link->getLabel());
        self::assertSame('/product/noma', $link->getUrl());
    }

    public function testEmptyNameYieldsNullLink(): void
    {
        $slot = $this->slot([
            'name' => '',
            'url' => '/product/noma',
            'imageMedia' => self::MEDIA_ID,
        ]);

        $media = $this->media(self::MEDIA_ID, 'https://cdn.example.com/noma.webp');
        $result = $this->resultForSlot($slot, null, $media);

        (new InlineProductTeaserCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(InlineProductTeaserStruct::class, $data);
        self::assertNull($data->getLink());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testManualModeRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'name' => 'Manual',
            'url' => $url,
            'imageMedia' => self::MEDIA_ID,
        ]);

        $media = $this->media(self::MEDIA_ID, 'https://cdn.example.com/manual.webp');
        $result = $this->resultForSlot($slot, null, $media);

        (new InlineProductTeaserCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(InlineProductTeaserStruct::class, $data);
        self::assertNull($data->getLink());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com/product'];
        yield 'relative without slash' => ['product/manual'];
    }

    public function testProductWithoutSeoUrlForCurrentChannelYieldsNullLink(): void
    {
        $slot = $this->slot(['productId' => self::PRODUCT_ID]);

        $product = $this->product(self::PRODUCT_ID, 'Sofa', 'produkt/sofa');
        $seoUrl = new SeoUrlEntity();
        $seoUrl->setUniqueIdentifier(Uuid::randomHex());
        $seoUrl->setSalesChannelId('other-channel');
        $seoUrl->setLanguageId(self::LANGUAGE_ID);
        $seoUrl->setSeoPathInfo('produkt/sofa');
        $seoUrl->setIsCanonical(true);
        $product->setSeoUrls(new SeoUrlCollection([$seoUrl]));

        $result = $this->resultForSlot($slot, $product, null);
        (new InlineProductTeaserCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(InlineProductTeaserStruct::class, $data);
        self::assertSame('Sofa', $data->getName());
        self::assertNull($data->getLink());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'name' => 'Noma',
            'url' => '/product/noma',
            'description' => '  ',
            'imageMedia' => self::MEDIA_ID,
        ]);

        $media = $this->media(self::MEDIA_ID, 'https://cdn.example.com/noma.webp', 'Noma');
        $result = $this->resultForSlot($slot, null, $media);

        (new InlineProductTeaserCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(InlineProductTeaserStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_inline_product_teaser', $payload['apiAlias']);
        self::assertNull($payload['productId']);
        self::assertSame('Noma', $payload['name']);
        self::assertNull($payload['description']);
        self::assertSame('cms_jv_inline_product_teaser_media', $payload['image']['apiAlias']);
        self::assertSame('cms_jv_inline_product_teaser_link', $payload['link']['apiAlias']);
    }

    private function resultForSlot(
        CmsSlotEntity $slot,
        ?SalesChannelProductEntity $product,
        ?MediaEntity $media,
    ): ElementDataCollection {
        $result = new ElementDataCollection();
        $slotKey = $slot->getUniqueIdentifier();

        if (null !== $product) {
            $result->add(
                'jv_inline_product_teaser_product_'.$slotKey,
                new EntitySearchResult(
                    ProductDefinition::ENTITY_NAME,
                    1,
                    new ProductCollection([$product]),
                    null,
                    new Criteria([$product->getUniqueIdentifier()]),
                    Context::createDefaultContext(),
                ),
            );
        }

        if (null !== $media) {
            $result->add(
                'jv_inline_product_teaser_media_'.$slotKey,
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

        return $result;
    }

    private function product(
        string $id,
        string $name,
        string $path,
        bool $withCover = true,
    ): SalesChannelProductEntity {
        $product = new SalesChannelProductEntity();
        $product->setUniqueIdentifier($id);
        $product->setId($id);
        $product->setTranslated(['name' => $name]);

        if ($withCover) {
            $media = new MediaEntity();
            $media->setUniqueIdentifier(Uuid::randomHex());
            $media->setUrl('https://cdn.example.com/product.webp');
            $media->setTranslated(['alt' => 'Product image']);

            $cover = new ProductMediaEntity();
            $cover->setUniqueIdentifier(Uuid::randomHex());
            $cover->setMedia($media);
            $product->setCover($cover);
        }

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setUniqueIdentifier(Uuid::randomHex());
        $seoUrl->setSalesChannelId(self::SALES_CHANNEL_ID);
        $seoUrl->setLanguageId(self::LANGUAGE_ID);
        $seoUrl->setSeoPathInfo(ltrim($path, '/'));
        $seoUrl->setIsCanonical(true);
        $product->setSeoUrls(new SeoUrlCollection([$seoUrl]));

        return $product;
    }

    private function media(string $id, string $url, string $alt = ''): MediaEntity
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier($id);
        $media->setId($id);
        $media->setUrl($url);
        if ('' !== $alt) {
            $media->setTranslated(['alt' => $alt]);
        } else {
            $media->setAlt('');
        }

        return $media;
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
     *     productId?: mixed,
     *     name?: string,
     *     description?: string,
     *     url?: string,
     *     imageMedia?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('productId', FieldConfig::SOURCE_STATIC, $values['productId'] ?? null));
        $collection->add(new FieldConfig('name', FieldConfig::SOURCE_STATIC, $values['name'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('url', FieldConfig::SOURCE_STATIC, $values['url'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-inline-product-teaser');
        $slot->setType(InlineProductTeaserCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        return $slot;
    }

    private function resolverContext(): ResolverContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getLanguageId')->willReturn(self::LANGUAGE_ID);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);

        return new ResolverContext($context, new Request());
    }
}
