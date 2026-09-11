<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\PromoBannerCmsElementResolver;
use Jv\Cms\DataResolver\Element\PromoBannerStruct;
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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class PromoBannerCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new PromoBannerCmsElementResolver();

        self::assertSame('jv-promo-banner', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => 'not-a-uuid']);

        self::assertNull((new PromoBannerCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => self::MEDIA_ID]);

        $criteriaCollection = (new PromoBannerCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_promo_banner_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_promo_banner_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new PromoBannerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(PromoBannerStruct::class, $data);
        self::assertSame('cms_jv_promo_banner', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertSame('right', $data->getContentPosition());
        self::assertNull($data->getImage());
        self::assertNull($data->getLink());
    }

    public function testItNormalizesHappyPathWithMediaMock(): void
    {
        $slot = $this->slot([
            'title' => '  Summer sale  ',
            'eyebrow' => '  Limited time  ',
            'description' => '  Up to 30 % off  ',
            'contentPosition' => 'left',
            'imageMedia' => self::MEDIA_ID,
            'link' => [
                'label' => '  Shop now  ',
                'url' => '  /sale  ',
                'size' => 'large',
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/banner.webp', 'Summer banner')]);
        (new PromoBannerCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(PromoBannerStruct::class, $data);
        self::assertSame('Summer sale', $data->getTitle());
        self::assertSame('Limited time', $data->getEyebrow());
        self::assertSame('Up to 30 % off', $data->getDescription());
        self::assertSame('left', $data->getContentPosition());

        $image = $data->getImage();
        self::assertNotNull($image);
        self::assertSame('cms_jv_promo_banner_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/banner.webp', $image->getUrl());
        self::assertSame('Summer banner', $image->getAlt());

        $link = $data->getLink();
        self::assertNotNull($link);
        self::assertSame('cms_jv_promo_banner_link', $link->getApiAlias());
        self::assertSame('Shop now', $link->getLabel());
        self::assertSame('/sale', $link->getUrl());
        self::assertSame('large', $link->getSize());
    }

    public function testInvalidMediaUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'imageMedia' => 'not-a-uuid',
        ]);

        (new PromoBannerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(PromoBannerStruct::class, $data);
        self::assertNull($data->getImage());
    }

    public function testValidMediaUuidMissingFromResultOmitsImage(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'imageMedia' => self::MEDIA_ID,
        ]);

        (new PromoBannerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(PromoBannerStruct::class, $data);
        self::assertNull($data->getImage());
    }

    public function testPartialLinkBecomesNull(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'link' => [
                'label' => 'Shop',
                'url' => '',
            ],
        ]);

        (new PromoBannerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(PromoBannerStruct::class, $data);
        self::assertNull($data->getLink());
    }

    public function testUnknownContentPositionDefaultsToRight(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'contentPosition' => 'center',
        ]);

        (new PromoBannerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(PromoBannerStruct::class, $data);
        self::assertSame('right', $data->getContentPosition());
    }

    public function testUnknownLinkSizeDefaultsToMedium(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'link' => [
                'label' => 'Shop',
                'url' => '/sale',
                'size' => 'xl',
            ],
        ]);

        (new PromoBannerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(PromoBannerStruct::class, $data);
        self::assertNotNull($data->getLink());
        self::assertSame('medium', $data->getLink()->getSize());
    }

    #[DataProvider('safePromoBannerHrefProvider')]
    public function testSafePromoBannerHrefAcceptsValidUrls(string $url, string $expected): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'link' => [
                'label' => 'CTA',
                'url' => $url,
            ],
        ]);

        (new PromoBannerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(PromoBannerStruct::class, $data);
        self::assertNotNull($data->getLink());
        self::assertSame($expected, $data->getLink()->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safePromoBannerHrefProvider(): iterable
    {
        yield 'relative' => ['/sale', '/sale'];
        yield 'trimmed relative' => ['  /dining  ', '/dining'];
        yield 'https' => ['https://example.com/path', 'https://example.com/path'];
        yield 'mailto' => ['mailto:hello@example.com', 'mailto:hello@example.com'];
    }

    #[DataProvider('unsafePromoBannerHrefProvider')]
    public function testSafePromoBannerHrefRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'link' => [
                'label' => 'CTA',
                'url' => $url,
            ],
        ]);

        (new PromoBannerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(PromoBannerStruct::class, $data);
        self::assertNull($data->getLink());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafePromoBannerHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['sale'];
        yield 'mailto without address' => ['mailto:'];
        yield 'mailto without at' => ['mailto:nope'];
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'eyebrow' => '',
            'description' => '  ',
            'contentPosition' => 'right',
            'imageMedia' => self::MEDIA_ID,
            'link' => [
                'label' => 'Shop',
                'url' => '/sale',
                'size' => 'medium',
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/banner.webp', 'Banner')]);
        (new PromoBannerCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(PromoBannerStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_promo_banner', $payload['apiAlias']);
        self::assertSame('Title', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertSame('right', $payload['contentPosition']);
        self::assertSame('cms_jv_promo_banner_media', $payload['image']['apiAlias']);
        self::assertSame('cms_jv_promo_banner_link', $payload['link']['apiAlias']);
        self::assertSame('medium', $payload['link']['size']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_promo_banner_media_'.$slot->getUniqueIdentifier(),
            new EntitySearchResult(
                MediaDefinition::ENTITY_NAME,
                \count($mediaEntities),
                new MediaCollection($mediaEntities),
                null,
                new Criteria(array_map(static fn (MediaEntity $media): string => $media->getUniqueIdentifier(), $mediaEntities)),
                Context::createDefaultContext(),
            ),
        );

        return $result;
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
            $media->setFileName('image.webp');
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
     *     title?: string,
     *     eyebrow?: string,
     *     description?: string,
     *     contentPosition?: string,
     *     imageMedia?: mixed,
     *     link?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('contentPosition', FieldConfig::SOURCE_STATIC, $values['contentPosition'] ?? 'right'));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
            'size' => 'medium',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-promo-banner');
        $slot->setType(PromoBannerCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

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
