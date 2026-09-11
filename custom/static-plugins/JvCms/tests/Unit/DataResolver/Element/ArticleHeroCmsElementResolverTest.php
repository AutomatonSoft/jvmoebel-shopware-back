<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ArticleHeroCmsElementResolver;
use Jv\Cms\DataResolver\Element\ArticleHeroStruct;
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

final class ArticleHeroCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new ArticleHeroCmsElementResolver();

        self::assertSame('jv-article-hero', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => 'not-a-uuid']);

        self::assertNull((new ArticleHeroCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => self::MEDIA_ID]);

        $criteriaCollection = (new ArticleHeroCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_article_hero_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_article_hero_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new ArticleHeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ArticleHeroStruct::class, $data);
        self::assertSame('cms_jv_article_hero', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertNull($data->getPublishedAt());
        self::assertNull($data->getReadTimeMinutes());
        self::assertNull($data->getImage());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'title' => '  How to style oak  ',
            'eyebrow' => '  Guide  ',
            'description' => '  Practical tips.  ',
            'publishedAt' => '  2026-03-01T10:00:00+01:00  ',
            'readTimeMinutes' => '  8  ',
            'imageMedia' => self::MEDIA_ID,
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/hero.webp', 'Oak table')]);
        (new ArticleHeroCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ArticleHeroStruct::class, $data);
        self::assertSame('How to style oak', $data->getTitle());
        self::assertSame('Guide', $data->getEyebrow());
        self::assertSame('Practical tips.', $data->getDescription());
        self::assertSame('2026-03-01T10:00:00+01:00', $data->getPublishedAt());
        self::assertSame(8, $data->getReadTimeMinutes());

        $image = $data->getImage();
        self::assertNotNull($image);
        self::assertSame('cms_jv_article_hero_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/hero.webp', $image->getUrl());
        self::assertSame('Oak table', $image->getAlt());
    }

    public function testInvalidPublishedAtBecomesNull(): void
    {
        $slot = $this->slot(['publishedAt' => 'not-a-date']);
        (new ArticleHeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ArticleHeroStruct::class, $data);
        self::assertNull($data->getPublishedAt());
    }

    public function testInvalidReadTimeMinutesBecomeNull(): void
    {
        $slot = $this->slot(['readTimeMinutes' => 0]);
        (new ArticleHeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ArticleHeroStruct::class, $data);
        self::assertNull($data->getReadTimeMinutes());
    }

    public function testNonDigitReadTimeMinutesBecomeNull(): void
    {
        $slot = $this->slot(['readTimeMinutes' => '8 minutes']);
        (new ArticleHeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ArticleHeroStruct::class, $data);
        self::assertNull($data->getReadTimeMinutes());
    }

    public function testValidMediaUuidMissingFromResultOmitsImage(): void
    {
        $slot = $this->slot(['imageMedia' => self::MEDIA_ID]);
        (new ArticleHeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ArticleHeroStruct::class, $data);
        self::assertNull($data->getImage());
    }

    public function testInvalidMediaUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->slot(['imageMedia' => 'not-a-uuid', 'title' => 'Title']);
        (new ArticleHeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ArticleHeroStruct::class, $data);
        self::assertSame('Title', $data->getTitle());
        self::assertNull($data->getImage());
    }

    public function testWhitespaceOptionalFieldsBecomeNull(): void
    {
        $slot = $this->slot([
            'eyebrow' => '  ',
            'description' => "\t",
        ]);

        (new ArticleHeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ArticleHeroStruct::class, $data);
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'eyebrow' => '',
            'description' => '  ',
            'publishedAt' => '2026-03-01',
            'readTimeMinutes' => 5,
            'imageMedia' => self::MEDIA_ID,
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/hero.webp')]);
        (new ArticleHeroCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ArticleHeroStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_article_hero', $payload['apiAlias']);
        self::assertSame('Title', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertSame('2026-03-01', $payload['publishedAt']);
        self::assertSame(5, $payload['readTimeMinutes']);
        self::assertSame('cms_jv_article_hero_media', $payload['image']['apiAlias']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_article_hero_media_'.$slot->getUniqueIdentifier(),
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
     *     publishedAt?: string,
     *     readTimeMinutes?: mixed,
     *     imageMedia?: string
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('publishedAt', FieldConfig::SOURCE_STATIC, $values['publishedAt'] ?? ''));
        $collection->add(new FieldConfig('readTimeMinutes', FieldConfig::SOURCE_STATIC, $values['readTimeMinutes'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-article-hero');
        $slot->setType(ArticleHeroCmsElementResolver::TYPE);
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
