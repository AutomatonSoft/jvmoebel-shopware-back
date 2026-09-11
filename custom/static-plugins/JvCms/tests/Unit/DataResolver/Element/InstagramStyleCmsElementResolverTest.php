<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\InstagramStyleCmsElementResolver;
use Jv\Cms\DataResolver\Element\InstagramStyleStruct;
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

final class InstagramStyleCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new InstagramStyleCmsElementResolver();

        self::assertSame('jv-instagram-style', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => 'not-a-uuid']);

        self::assertNull((new InstagramStyleCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => self::MEDIA_ID]);

        $criteriaCollection = (new InstagramStyleCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_instagram_style_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_instagram_style_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new InstagramStyleCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(InstagramStyleStruct::class, $data);
        self::assertSame('cms_jv_instagram_style', $data->getApiAlias());
        self::assertSame('', $data->getHandle());
        self::assertNull($data->getCaption());
        self::assertNull($data->getImage());
        self::assertNull($data->getLink());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'handle' => '  @jvmoebel  ',
            'caption' => '  New collection  ',
            'imageMedia' => self::MEDIA_ID,
            'link' => [
                'label' => '  Follow us  ',
                'url' => '  https://instagram.com/jvmoebel  ',
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/insta.webp', 'Instagram post')]);
        (new InstagramStyleCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(InstagramStyleStruct::class, $data);
        self::assertSame('jvmoebel', $data->getHandle());
        self::assertSame('New collection', $data->getCaption());

        $image = $data->getImage();
        self::assertNotNull($image);
        self::assertSame('cms_jv_instagram_style_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/insta.webp', $image->getUrl());
        self::assertSame('Instagram post', $image->getAlt());

        $link = $data->getLink();
        self::assertNotNull($link);
        self::assertSame('cms_jv_instagram_style_link', $link->getApiAlias());
        self::assertSame('Follow us', $link->getLabel());
        self::assertSame('https://instagram.com/jvmoebel', $link->getUrl());
    }

    #[DataProvider('handleProvider')]
    public function testHandleStripsLeadingAtSign(string $input, string $expected): void
    {
        $slot = $this->slot(['handle' => $input]);
        (new InstagramStyleCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(InstagramStyleStruct::class, $data);
        self::assertSame($expected, $data->getHandle());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function handleProvider(): iterable
    {
        yield 'with at sign' => ['@jvmoebel', 'jvmoebel'];
        yield 'without at sign' => ['jvmoebel', 'jvmoebel'];
        yield 'trimmed with at sign' => ['  @jvmoebel  ', 'jvmoebel'];
        yield 'multiple leading at signs stripped' => ['@@jvmoebel', 'jvmoebel'];
        yield 'empty' => ['', ''];
        yield 'only at sign' => ['@', ''];
    }

    public function testInvalidMediaUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->slot([
            'handle' => 'jvmoebel',
            'imageMedia' => 'not-a-uuid',
        ]);

        (new InstagramStyleCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(InstagramStyleStruct::class, $data);
        self::assertNull($data->getImage());
    }

    public function testValidMediaUuidMissingFromResultOmitsImage(): void
    {
        $slot = $this->slot([
            'handle' => 'jvmoebel',
            'imageMedia' => self::MEDIA_ID,
        ]);

        (new InstagramStyleCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(InstagramStyleStruct::class, $data);
        self::assertNull($data->getImage());
    }

    public function testPartialLinkBecomesNull(): void
    {
        $slot = $this->slot([
            'handle' => 'jvmoebel',
            'link' => [
                'label' => 'Follow us',
                'url' => '',
            ],
        ]);

        (new InstagramStyleCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(InstagramStyleStruct::class, $data);
        self::assertNull($data->getLink());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testUnsafeLinkUrlBecomesNull(string $url): void
    {
        $slot = $this->slot([
            'handle' => 'jvmoebel',
            'link' => [
                'label' => 'Follow us',
                'url' => $url,
            ],
        ]);

        (new InstagramStyleCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(InstagramStyleStruct::class, $data);
        self::assertNull($data->getLink());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['instagram.com/jvmoebel'];
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'handle' => '@jvmoebel',
            'caption' => '',
            'imageMedia' => self::MEDIA_ID,
            'link' => [
                'label' => 'Follow us',
                'url' => 'https://instagram.com/jvmoebel',
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/insta.webp', 'Post')]);
        (new InstagramStyleCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(InstagramStyleStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_instagram_style', $payload['apiAlias']);
        self::assertSame('jvmoebel', $payload['handle']);
        self::assertNull($payload['caption']);
        self::assertSame('cms_jv_instagram_style_media', $payload['image']['apiAlias']);
        self::assertSame('cms_jv_instagram_style_link', $payload['link']['apiAlias']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_instagram_style_media_'.$slot->getUniqueIdentifier(),
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
     *     handle?: string,
     *     caption?: string,
     *     imageMedia?: mixed,
     *     link?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('handle', FieldConfig::SOURCE_STATIC, $values['handle'] ?? ''));
        $collection->add(new FieldConfig('caption', FieldConfig::SOURCE_STATIC, $values['caption'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-instagram-style');
        $slot->setType(InstagramStyleCmsElementResolver::TYPE);
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
