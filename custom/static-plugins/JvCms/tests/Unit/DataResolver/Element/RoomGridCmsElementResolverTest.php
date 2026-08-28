<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\RoomGridCmsElementResolver;
use Jv\Cms\DataResolver\Element\RoomGridStruct;
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

final class RoomGridCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string MEDIA_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new RoomGridCmsElementResolver();

        self::assertSame('jv-room-grid', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot([
            'rooms' => [[
                'label' => 'Living',
                'title' => 'Sofas',
                'url' => '/living',
                'imageMedia' => 'not-a-uuid',
            ]],
        ]);

        self::assertNull((new RoomGridCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot([
            'rooms' => [[
                'label' => 'Living',
                'title' => 'Sofas',
                'url' => '/living',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $criteriaCollection = (new RoomGridCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_room_grid_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_room_grid_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testCollectDedupesMediaIds(): void
    {
        $slot = $this->slot([
            'rooms' => [
                [
                    'label' => 'Living',
                    'title' => 'Sofas',
                    'url' => '/living',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'label' => 'Dining',
                    'title' => 'Tables',
                    'url' => '/dining',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'label' => 'Bedroom',
                    'title' => 'Beds',
                    'url' => '/bedroom',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $criteriaCollection = (new RoomGridCmsElementResolver())->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $named = $criteriaCollection->all()[MediaDefinition::class];
        $ids = $named['jv_room_grid_media_'.$slot->getUniqueIdentifier()]->getIds();
        sort($ids);

        self::assertSame([self::MEDIA_ID, self::MEDIA_ID_2], $ids);
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertSame('cms_jv_room_grid', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertSame([], $data->getRooms());
    }

    public function testItNormalizesHappyPathWithTwoRooms(): void
    {
        $slot = $this->slot([
            'title' => '  Furniture for every room.  ',
            'eyebrow' => '  Shop by room  ',
            'description' => '  Discover pieces.  ',
            'rooms' => [
                [
                    'id' => 'living-room',
                    'position' => 1,
                    'featured' => true,
                    'label' => '  Living room  ',
                    'title' => '  Sofas and tables  ',
                    'url' => '  /living  ',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'dining-room',
                    'position' => 0,
                    'featured' => 1,
                    'label' => 'Dining',
                    'title' => 'Tables',
                    'url' => 'https://example.com/dining',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $media1 = $this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp', 'Living room');
        $media2 = $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/dining.webp', 'Dining room');

        $result = new ElementDataCollection();
        $result->add(
            'jv_room_grid_media_'.$slot->getUniqueIdentifier(),
            new EntitySearchResult(
                MediaDefinition::ENTITY_NAME,
                2,
                new MediaCollection([$media1, $media2]),
                null,
                new Criteria([self::MEDIA_ID, self::MEDIA_ID_2]),
                Context::createDefaultContext(),
            ),
        );

        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertSame('Furniture for every room.', $data->getTitle());
        self::assertSame('Shop by room', $data->getEyebrow());
        self::assertSame('Discover pieces.', $data->getDescription());
        self::assertCount(2, $data->getRooms());

        self::assertSame('dining-room', $data->getRooms()[0]->getId());
        self::assertSame(0, $data->getRooms()[0]->getPosition());
        self::assertTrue($data->getRooms()[0]->isFeatured());

        self::assertSame('living-room', $data->getRooms()[1]->getId());
        self::assertSame(1, $data->getRooms()[1]->getPosition());
        self::assertTrue($data->getRooms()[1]->isFeatured());

        $image = $data->getRooms()[0]->getImage();
        self::assertSame('cms_jv_room_grid_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/dining.webp', $image->getUrl());
        self::assertSame('Dining room', $image->getAlt());
    }

    public function testKeyedObjectConfigIsNormalizedToArray(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'rooms' => [
                'living' => [
                    'id' => 'living-room',
                    'label' => 'Living',
                    'title' => 'Sofas',
                    'url' => '/living',
                    'imageMedia' => self::MEDIA_ID,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp')]);
        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertCount(1, $data->getRooms());
        self::assertSame('living-room', $data->getRooms()[0]->getId());
    }

    public function testDuplicateIdsKeepFirstValidRoom(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'rooms' => [
                [
                    'id' => 'living-room',
                    'label' => 'Living',
                    'title' => 'First',
                    'url' => '/living',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'living-room',
                    'label' => 'Living duplicate',
                    'title' => 'Second',
                    'url' => '/living-2',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/first.webp'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/second.webp'),
        ]);

        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertCount(1, $data->getRooms());
        self::assertSame('First', $data->getRooms()[0]->getTitle());
    }

    public function testEmptyIdFallsBackToLabelAndOriginalIndex(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'rooms' => [[
                'label' => 'Living',
                'title' => 'Sofas',
                'url' => '/living',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp')]);
        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertSame('Living-0', $data->getRooms()[0]->getId());
    }

    public function testPartialRoomsAreSkipped(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'rooms' => [
                [
                    'label' => '',
                    'title' => 'Broken',
                    'url' => '/broken',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'label' => 'Dining',
                    'title' => 'Tables',
                    'url' => '/dining',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/broken.webp'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/dining.webp'),
        ]);

        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertCount(1, $data->getRooms());
        self::assertSame('Dining', $data->getRooms()[0]->getLabel());
    }

    public function testValidMediaUuidMissingFromResultOmitsRoom(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'rooms' => [[
                'label' => 'Living',
                'title' => 'Sofas',
                'url' => '/living',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertSame([], $data->getRooms());
    }

    public function testInvalidMediaUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'rooms' => [[
                'label' => 'Living',
                'title' => 'Sofas',
                'url' => '/living',
                'imageMedia' => 'not-a-uuid',
            ]],
        ]);

        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertSame([], $data->getRooms());
    }

    public function testNonArrayRoomsConfigYieldsEmptyRooms(): void
    {
        $slot = $this->slot(['rooms' => 'broken']);
        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertSame([], $data->getRooms());
    }

    public function testFeaturedFalseForNonTruthyValues(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'rooms' => [[
                'label' => 'Living',
                'title' => 'Sofas',
                'url' => '/living',
                'featured' => 'yes',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp')]);
        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertFalse($data->getRooms()[0]->isFeatured());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'eyebrow' => '',
            'description' => '  ',
            'rooms' => [[
                'id' => 'living-room',
                'label' => 'Living',
                'title' => 'Sofas',
                'url' => '/living',
                'featured' => 1,
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp', 'Living')]);
        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_room_grid', $payload['apiAlias']);
        self::assertSame('Title', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertIsArray($payload['rooms']);
        self::assertSame('cms_jv_room_grid_room', $payload['rooms'][0]['apiAlias']);
        self::assertTrue($payload['rooms'][0]['featured']);
        self::assertSame('cms_jv_room_grid_media', $payload['rooms'][0]['image']['apiAlias']);
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsRelativeAndAbsoluteUrls(string $url, string $expected): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'rooms' => [[
                'label' => 'Living',
                'title' => 'Sofas',
                'url' => $url,
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp')]);
        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertSame($expected, $data->getRooms()[0]->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'relative' => ['/living', '/living'];
        yield 'query string' => ['/shop?q=1', '/shop?q=1'];
        yield 'https' => ['https://example.com/path', 'https://example.com/path'];
        yield 'trimmed relative' => ['  /dining  ', '/dining'];
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'rooms' => [[
                'label' => 'Living',
                'title' => 'Sofas',
                'url' => $url,
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp')]);
        (new RoomGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertSame([], $data->getRooms());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['living'];
        yield 'https without host' => ['https://'];
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_room_grid_media_'.$slot->getUniqueIdentifier(),
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
     *     rooms?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('rooms', FieldConfig::SOURCE_STATIC, $values['rooms'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-room-grid');
        $slot->setType(RoomGridCmsElementResolver::TYPE);
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
