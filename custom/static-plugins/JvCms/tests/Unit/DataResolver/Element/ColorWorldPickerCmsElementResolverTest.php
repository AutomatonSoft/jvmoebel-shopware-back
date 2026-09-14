<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ColorWorldPickerCmsElementResolver;
use Jv\Cms\DataResolver\Element\ColorWorldPickerStruct;
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

final class ColorWorldPickerCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string MEDIA_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new ColorWorldPickerCmsElementResolver();

        self::assertSame('jv-color-world-picker', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot([
            'colors' => [[
                'name' => 'Sand',
                'url' => '/colors/sand',
                'imageMedia' => 'not-a-uuid',
            ]],
        ]);

        self::assertNull((new ColorWorldPickerCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot([
            'colors' => [[
                'name' => 'Sand',
                'url' => '/colors/sand',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $criteriaCollection = (new ColorWorldPickerCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_color_world_picker_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_color_world_picker_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testCollectDedupesMediaIds(): void
    {
        $slot = $this->slot([
            'colors' => [
                [
                    'name' => 'Sand',
                    'url' => '/colors/sand',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'name' => 'Clay',
                    'url' => '/colors/clay',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'name' => 'Stone',
                    'url' => '/colors/stone',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $criteriaCollection = (new ColorWorldPickerCmsElementResolver())->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $named = $criteriaCollection->all()[MediaDefinition::class];
        $ids = $named['jv_color_world_picker_media_'.$slot->getUniqueIdentifier()]->getIds();
        sort($ids);

        self::assertSame([self::MEDIA_ID, self::MEDIA_ID_2], $ids);
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertSame('cms_jv_color_world_picker', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getDescription());
        self::assertSame([], $data->getColors());
    }

    public function testItNormalizesHappyPathWithTwoColors(): void
    {
        $slot = $this->slot([
            'title' => '  Choose your palette  ',
            'description' => '  Warm tones.  ',
            'colors' => [
                [
                    'id' => 'sand',
                    'position' => 1,
                    'name' => '  Sand  ',
                    'url' => '  /colors/sand  ',
                    'hex' => '  #abc  ',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'clay',
                    'position' => 0,
                    'name' => 'Clay',
                    'url' => 'https://example.com/clay',
                    'hex' => '#112233',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $media1 = $this->media(self::MEDIA_ID, 'https://cdn.example.com/sand.webp', 'Sand swatch');
        $media2 = $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/clay.webp', 'Clay swatch');

        $result = $this->resultForSlot($slot, [$media1, $media2]);
        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertSame('Choose your palette', $data->getTitle());
        self::assertSame('Warm tones.', $data->getDescription());
        self::assertCount(2, $data->getColors());

        self::assertSame('clay', $data->getColors()[0]->getId());
        self::assertSame(0, $data->getColors()[0]->getPosition());
        self::assertSame('Clay', $data->getColors()[0]->getName());
        self::assertSame('https://example.com/clay', $data->getColors()[0]->getUrl());

        $clayImage = $data->getColors()[0]->getImage();
        self::assertNotNull($clayImage);
        self::assertSame('cms_jv_color_world_picker_color_media', $clayImage->getApiAlias());
        self::assertSame('https://cdn.example.com/clay.webp', $clayImage->getUrl());
        self::assertSame('Clay swatch', $clayImage->getAlt());

        self::assertSame('sand', $data->getColors()[1]->getId());
        self::assertSame('#ABC', $data->getColors()[1]->getVars()['hex'] ?? null);
    }

    public function testKeyedObjectConfigIsNormalizedToArray(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'colors' => [
                'sand' => [
                    'id' => 'sand',
                    'name' => 'Sand',
                    'url' => '/colors/sand',
                    'imageMedia' => self::MEDIA_ID,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/sand.webp')]);
        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertCount(1, $data->getColors());
        self::assertSame('sand', $data->getColors()[0]->getId());
    }

    public function testDuplicateIdsKeepFirstValidColor(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'colors' => [
                [
                    'id' => 'sand',
                    'name' => 'Sand',
                    'url' => '/colors/sand',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'sand',
                    'name' => 'Sand duplicate',
                    'url' => '/colors/sand-2',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/sand.webp'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/sand-2.webp'),
        ]);

        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertCount(1, $data->getColors());
        self::assertSame('Sand', $data->getColors()[0]->getName());
    }

    public function testEmptyIdFallsBackToNameAndOriginalIndex(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'colors' => [[
                'name' => 'Sand',
                'url' => '/colors/sand',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/sand.webp')]);
        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertSame('Sand-0', $data->getColors()[0]->getId());
    }

    public function testPartialColorsAreSkipped(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'colors' => [
                [
                    'name' => '',
                    'url' => '/broken',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'name' => 'Clay',
                    'url' => '/colors/clay',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/broken.webp'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/clay.webp'),
        ]);

        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertCount(1, $data->getColors());
        self::assertSame('Clay', $data->getColors()[0]->getName());
    }

    public function testColorWithoutImageMediaIsStillIncluded(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'colors' => [[
                'name' => 'Sand',
                'url' => '/colors/sand',
                'hex' => '#FFAA00',
            ]],
        ]);

        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertCount(1, $data->getColors());
        self::assertNull($data->getColors()[0]->getImage());
        self::assertSame('#FFAA00', $data->getColors()[0]->getVars()['hex'] ?? null);
    }

    public function testInvalidHexIsOmitted(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'colors' => [[
                'name' => 'Sand',
                'url' => '/colors/sand',
                'hex' => 'not-a-hex',
            ]],
        ]);

        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertArrayNotHasKey('hex', $data->getColors()[0]->getVars());
    }

    public function testValidMediaUuidMissingFromResultOmitsImage(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'colors' => [[
                'name' => 'Sand',
                'url' => '/colors/sand',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertCount(1, $data->getColors());
        self::assertNull($data->getColors()[0]->getImage());
    }

    public function testInvalidMediaUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'colors' => [[
                'name' => 'Sand',
                'url' => '/colors/sand',
                'imageMedia' => 'not-a-uuid',
            ]],
        ]);

        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertCount(1, $data->getColors());
        self::assertNull($data->getColors()[0]->getImage());
    }

    public function testNonArrayColorsConfigYieldsEmptyColors(): void
    {
        $slot = $this->slot(['colors' => 'broken']);
        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertSame([], $data->getColors());
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsRelativeAndAbsoluteUrls(string $url, string $expected): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'colors' => [[
                'name' => 'Sand',
                'url' => $url,
            ]],
        ]);

        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertSame($expected, $data->getColors()[0]->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'relative' => ['/colors/sand', '/colors/sand'];
        yield 'query string' => ['/shop?q=1', '/shop?q=1'];
        yield 'https' => ['https://example.com/path', 'https://example.com/path'];
        yield 'trimmed relative' => ['  /colors/clay  ', '/colors/clay'];
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'colors' => [[
                'name' => 'Sand',
                'url' => $url,
            ]],
        ]);

        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertSame([], $data->getColors());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['colors/sand'];
        yield 'https without host' => ['https://'];
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'description' => '  ',
            'colors' => [[
                'id' => 'sand',
                'name' => 'Sand',
                'url' => '/colors/sand',
                'hex' => '#AABBCC',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/sand.webp', 'Sand')]);
        (new ColorWorldPickerCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_color_world_picker', $payload['apiAlias']);
        self::assertSame('Title', $payload['title']);
        self::assertNull($payload['description']);
        self::assertSame('cms_jv_color_world_picker_color', $payload['colors'][0]['apiAlias']);
        self::assertSame('cms_jv_color_world_picker_color_media', $payload['colors'][0]['image']['apiAlias']);
        self::assertSame('#AABBCC', $payload['colors'][0]['hex']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_color_world_picker_media_'.$slot->getUniqueIdentifier(),
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
     *     description?: string,
     *     colors?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('colors', FieldConfig::SOURCE_STATIC, $values['colors'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-color-world-picker');
        $slot->setType(ColorWorldPickerCmsElementResolver::TYPE);
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
