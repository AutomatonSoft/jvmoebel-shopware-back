<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\AuthorFooterCmsElementResolver;
use Jv\Cms\DataResolver\Element\AuthorFooterStruct;
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

final class AuthorFooterCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new AuthorFooterCmsElementResolver();

        self::assertSame('jv-author-footer', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => 'not-a-uuid']);

        self::assertNull((new AuthorFooterCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => self::MEDIA_ID]);

        $criteriaCollection = (new AuthorFooterCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_author_footer_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_author_footer_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new AuthorFooterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(AuthorFooterStruct::class, $data);
        self::assertSame('cms_jv_author_footer', $data->getApiAlias());
        self::assertSame('', $data->getAuthorName());
        self::assertNull($data->getExpertise());
        self::assertNull($data->getBio());
        self::assertNull($data->getImage());
        self::assertNull($data->getLink());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'authorName' => '  Anna Müller  ',
            'expertise' => '  Interior design  ',
            'bio' => '  Writes about living spaces.  ',
            'imageMedia' => self::MEDIA_ID,
            'link' => [
                'label' => '  Read more  ',
                'url' => '  /authors/anna  ',
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/anna.webp', 'Anna Müller')]);
        (new AuthorFooterCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(AuthorFooterStruct::class, $data);
        self::assertSame('Anna Müller', $data->getAuthorName());
        self::assertSame('Interior design', $data->getExpertise());
        self::assertSame('Writes about living spaces.', $data->getBio());

        $image = $data->getImage();
        self::assertNotNull($image);
        self::assertSame('cms_jv_author_footer_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/anna.webp', $image->getUrl());
        self::assertSame('Anna Müller', $image->getAlt());

        $link = $data->getLink();
        self::assertNotNull($link);
        self::assertSame('cms_jv_author_footer_link', $link->getApiAlias());
        self::assertSame('Read more', $link->getLabel());
        self::assertSame('/authors/anna', $link->getUrl());
    }

    public function testInvalidMediaUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->slot([
            'authorName' => 'Anna',
            'imageMedia' => 'not-a-uuid',
        ]);

        (new AuthorFooterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(AuthorFooterStruct::class, $data);
        self::assertNull($data->getImage());
    }

    public function testValidMediaUuidMissingFromResultOmitsImage(): void
    {
        $slot = $this->slot([
            'authorName' => 'Anna',
            'imageMedia' => self::MEDIA_ID,
        ]);

        (new AuthorFooterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(AuthorFooterStruct::class, $data);
        self::assertNull($data->getImage());
    }

    public function testPartialLinkBecomesNull(): void
    {
        $slot = $this->slot([
            'authorName' => 'Anna',
            'link' => [
                'label' => 'Read more',
                'url' => '',
            ],
        ]);

        (new AuthorFooterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(AuthorFooterStruct::class, $data);
        self::assertNull($data->getLink());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testUnsafeLinkUrlBecomesNull(string $url): void
    {
        $slot = $this->slot([
            'authorName' => 'Anna',
            'link' => [
                'label' => 'Read more',
                'url' => $url,
            ],
        ]);

        (new AuthorFooterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(AuthorFooterStruct::class, $data);
        self::assertNull($data->getLink());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['authors/anna'];
    }

    #[DataProvider('safeHrefProvider')]
    public function testLinkAcceptsSafeUrls(string $url, string $expected): void
    {
        $slot = $this->slot([
            'authorName' => 'Anna',
            'link' => [
                'label' => 'Read more',
                'url' => $url,
            ],
        ]);

        (new AuthorFooterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(AuthorFooterStruct::class, $data);
        self::assertNotNull($data->getLink());
        self::assertSame($expected, $data->getLink()->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'relative' => ['/authors/anna', '/authors/anna'];
        yield 'https' => ['https://example.com/authors/anna', 'https://example.com/authors/anna'];
        yield 'trimmed relative' => ['  /authors/anna  ', '/authors/anna'];
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'authorName' => 'Anna',
            'expertise' => '',
            'bio' => '  ',
            'imageMedia' => self::MEDIA_ID,
            'link' => [
                'label' => 'Read more',
                'url' => '/authors/anna',
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/anna.webp', 'Anna')]);
        (new AuthorFooterCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(AuthorFooterStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_author_footer', $payload['apiAlias']);
        self::assertSame('Anna', $payload['authorName']);
        self::assertNull($payload['expertise']);
        self::assertNull($payload['bio']);
        self::assertSame('cms_jv_author_footer_media', $payload['image']['apiAlias']);
        self::assertSame('cms_jv_author_footer_link', $payload['link']['apiAlias']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_author_footer_media_'.$slot->getUniqueIdentifier(),
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
     *     authorName?: string,
     *     expertise?: string,
     *     bio?: string,
     *     imageMedia?: mixed,
     *     link?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('authorName', FieldConfig::SOURCE_STATIC, $values['authorName'] ?? ''));
        $collection->add(new FieldConfig('expertise', FieldConfig::SOURCE_STATIC, $values['expertise'] ?? ''));
        $collection->add(new FieldConfig('bio', FieldConfig::SOURCE_STATIC, $values['bio'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-author-footer');
        $slot->setType(AuthorFooterCmsElementResolver::TYPE);
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
