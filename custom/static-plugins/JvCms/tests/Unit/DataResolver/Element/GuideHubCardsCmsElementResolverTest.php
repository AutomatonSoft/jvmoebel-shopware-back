<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\GuideHubCardsCmsElementResolver;
use Jv\Cms\DataResolver\Element\GuideHubCardsStruct;
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

final class GuideHubCardsCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string MEDIA_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new GuideHubCardsCmsElementResolver();

        self::assertSame('jv-guide-hub-cards', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot([
            'cards' => [[
                'title' => 'Living room',
                'url' => '/guides/living',
                'imageMedia' => 'not-a-uuid',
            ]],
        ]);

        self::assertNull((new GuideHubCardsCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot([
            'cards' => [[
                'title' => 'Living room',
                'url' => '/guides/living',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $criteriaCollection = (new GuideHubCardsCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_guide_hub_cards_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_guide_hub_cards_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testCollectDedupesMediaIds(): void
    {
        $slot = $this->slot([
            'cards' => [
                [
                    'title' => 'Living room',
                    'url' => '/guides/living',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Dining room',
                    'url' => '/guides/dining',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Bedroom',
                    'url' => '/guides/bedroom',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $criteriaCollection = (new GuideHubCardsCmsElementResolver())->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $named = $criteriaCollection->all()[MediaDefinition::class];
        $ids = $named['jv_guide_hub_cards_media_'.$slot->getUniqueIdentifier()]->getIds();
        sort($ids);

        self::assertSame([self::MEDIA_ID, self::MEDIA_ID_2], $ids);
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new GuideHubCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(GuideHubCardsStruct::class, $data);
        self::assertSame('cms_jv_guide_hub_cards', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertSame([], $data->getCards());
    }

    public function testItNormalizesHappyPathWithTwoCards(): void
    {
        $slot = $this->slot([
            'title' => '  Guide hub  ',
            'eyebrow' => '  Inspiration  ',
            'cards' => [
                [
                    'id' => 'living',
                    'position' => 1,
                    'title' => '  Living room  ',
                    'description' => '  Sofas and tables  ',
                    'url' => '  /guides/living  ',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'dining',
                    'position' => 0,
                    'title' => 'Dining room',
                    'url' => 'https://example.com/guides/dining',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $media1 = $this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp', 'Living room');
        $media2 = $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/dining.webp', 'Dining room');

        $result = $this->resultForSlot($slot, [$media1, $media2]);
        (new GuideHubCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(GuideHubCardsStruct::class, $data);
        self::assertSame('Guide hub', $data->getTitle());
        self::assertSame('Inspiration', $data->getEyebrow());
        self::assertCount(2, $data->getCards());

        self::assertSame('dining', $data->getCards()[0]->getId());
        self::assertSame(0, $data->getCards()[0]->getPosition());
        self::assertSame('Dining room', $data->getCards()[0]->getTitle());
        self::assertNull($data->getCards()[0]->getDescription());

        self::assertSame('living', $data->getCards()[1]->getId());
        self::assertSame(1, $data->getCards()[1]->getPosition());
        self::assertSame('Sofas and tables', $data->getCards()[1]->getDescription());
        self::assertSame('/guides/living', $data->getCards()[1]->getUrl());

        $image = $data->getCards()[0]->getImage();
        self::assertSame('cms_jv_guide_hub_cards_card_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/dining.webp', $image->getUrl());
    }

    public function testKeyedObjectConfigIsNormalizedToArray(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'cards' => [
                'living' => [
                    'id' => 'living-room',
                    'title' => 'Living room',
                    'url' => '/guides/living',
                    'imageMedia' => self::MEDIA_ID,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp')]);
        (new GuideHubCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(GuideHubCardsStruct::class, $data);
        self::assertCount(1, $data->getCards());
        self::assertSame('living-room', $data->getCards()[0]->getId());
    }

    public function testDuplicateIdsKeepFirstValidCard(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'cards' => [
                [
                    'id' => 'living',
                    'title' => 'First',
                    'url' => '/guides/living',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'living',
                    'title' => 'Second',
                    'url' => '/guides/living-2',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/first.webp'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/second.webp'),
        ]);

        (new GuideHubCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(GuideHubCardsStruct::class, $data);
        self::assertCount(1, $data->getCards());
        self::assertSame('First', $data->getCards()[0]->getTitle());
    }

    public function testCardWithoutImageIsSkipped(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'cards' => [
                [
                    'title' => 'Broken',
                    'url' => '/guides/broken',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Valid',
                    'url' => '/guides/valid',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/valid.webp'),
        ]);

        (new GuideHubCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(GuideHubCardsStruct::class, $data);
        self::assertCount(1, $data->getCards());
        self::assertSame('Valid', $data->getCards()[0]->getTitle());
    }

    public function testPartialCardsAreSkipped(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'cards' => [
                [
                    'title' => '',
                    'url' => '/guides/broken',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Valid',
                    'url' => '/guides/valid',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/broken.webp'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/valid.webp'),
        ]);

        (new GuideHubCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(GuideHubCardsStruct::class, $data);
        self::assertCount(1, $data->getCards());
        self::assertSame('Valid', $data->getCards()[0]->getTitle());
    }

    public function testNonArrayCardsConfigYieldsEmptyCards(): void
    {
        $slot = $this->slot(['cards' => 'broken']);
        (new GuideHubCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(GuideHubCardsStruct::class, $data);
        self::assertSame([], $data->getCards());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'cards' => [[
                'title' => 'Living room',
                'url' => $url,
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp')]);
        (new GuideHubCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(GuideHubCardsStruct::class, $data);
        self::assertSame([], $data->getCards());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['guides/living'];
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'eyebrow' => '',
            'cards' => [[
                'id' => 'living',
                'title' => 'Living room',
                'url' => '/guides/living',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp', 'Living')]);
        (new GuideHubCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(GuideHubCardsStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_guide_hub_cards', $payload['apiAlias']);
        self::assertSame('Title', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertSame('cms_jv_guide_hub_cards_card', $payload['cards'][0]['apiAlias']);
        self::assertSame('cms_jv_guide_hub_cards_card_media', $payload['cards'][0]['image']['apiAlias']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_guide_hub_cards_media_'.$slot->getUniqueIdentifier(),
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
     *     cards?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('cards', FieldConfig::SOURCE_STATIC, $values['cards'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-guide-hub-cards');
        $slot->setType(GuideHubCardsCmsElementResolver::TYPE);
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
