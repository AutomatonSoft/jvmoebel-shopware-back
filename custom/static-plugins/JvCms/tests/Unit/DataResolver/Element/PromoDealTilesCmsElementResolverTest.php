<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\PromoDealTilesCmsElementResolver;
use Jv\Cms\DataResolver\Element\PromoDealTilesStruct;
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

final class PromoDealTilesCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string MEDIA_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new PromoDealTilesCmsElementResolver();

        self::assertSame('jv-promo-deal-tiles', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot([
            'tiles' => [[
                'label' => 'Sofas',
                'link' => ['url' => '/sale/sofas'],
                'imageMedia' => 'not-a-uuid',
            ]],
        ]);

        self::assertNull((new PromoDealTilesCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectDedupesMediaIds(): void
    {
        $slot = $this->slot([
            'tiles' => [
                [
                    'label' => 'Sofas',
                    'link' => ['url' => '/sale/sofas'],
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'label' => 'Tables',
                    'link' => ['url' => '/sale/tables'],
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'label' => 'Beds',
                    'link' => ['url' => '/sale/beds'],
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $criteriaCollection = (new PromoDealTilesCmsElementResolver())->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $named = $criteriaCollection->all()[MediaDefinition::class];
        $ids = $named['jv_promo_deal_tiles_media_'.$slot->getUniqueIdentifier()]->getIds();
        sort($ids);

        self::assertSame([self::MEDIA_ID, self::MEDIA_ID_2], $ids);
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new PromoDealTilesCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(PromoDealTilesStruct::class, $data);
        self::assertSame('cms_jv_promo_deal_tiles', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertSame([], $data->getTiles());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'title' => '  Homie Days  ',
            'eyebrow' => '  Nur bis Sonntag  ',
            'tiles' => [
                [
                    'id' => 'sofas',
                    'position' => 1,
                    'label' => '  Sofas  ',
                    'description' => '  Bis zu 40 %  ',
                    'discountLabel' => '  -40 %  ',
                    'endsAt' => '  2026-09-14T23:59:59+02:00  ',
                    'imageMedia' => self::MEDIA_ID,
                    'link' => [
                        'label' => '  Entdecken  ',
                        'url' => '  /sale/sofas  ',
                        'size' => 'large',
                    ],
                ],
                [
                    'id' => 'tables',
                    'position' => 0,
                    'label' => 'Tables',
                    'description' => '',
                    'discountLabel' => '',
                    'endsAt' => 'invalid',
                    'imageMedia' => self::MEDIA_ID_2,
                    'link' => [
                        'label' => 'Explore',
                        'url' => 'https://example.com/tables',
                        'size' => 'unknown',
                    ],
                ],
            ],
        ]);

        $media1 = $this->media(self::MEDIA_ID, '/media/deal-sofas.webp', 'Sofas');
        $media2 = $this->media(self::MEDIA_ID_2, '/media/deal-tables.webp', 'Tables');

        $result = $this->resultForSlot($slot, [$media1, $media2]);
        (new PromoDealTilesCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(PromoDealTilesStruct::class, $data);
        self::assertSame('Homie Days', $data->getTitle());
        self::assertSame('Nur bis Sonntag', $data->getEyebrow());
        self::assertCount(2, $data->getTiles());

        self::assertSame('tables', $data->getTiles()[0]->getId());
        self::assertSame(0, $data->getTiles()[0]->getPosition());
        self::assertNull($data->getTiles()[0]->getEndsAt());
        self::assertNull($data->getTiles()[0]->getDescription());
        self::assertNull($data->getTiles()[0]->getDiscountLabel());
        self::assertSame('https://example.com/tables', $data->getTiles()[0]->getUrl());
        self::assertNotNull($data->getTiles()[0]->getLink());
        self::assertSame('medium', $data->getTiles()[0]->getLink()->getSize());

        self::assertSame('sofas', $data->getTiles()[1]->getId());
        self::assertSame('Sofas', $data->getTiles()[1]->getLabel());
        self::assertSame('2026-09-14T23:59:59+02:00', $data->getTiles()[1]->getEndsAt());
        self::assertSame('cms_jv_promo_deal_tiles_tile_media', $data->getTiles()[1]->getImage()->getApiAlias());
    }

    public function testEmptyIdFallsBackToTileIndex(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'tiles' => [[
                'label' => 'Sofas',
                'imageMedia' => self::MEDIA_ID,
                'link' => ['url' => '/sale/sofas', 'label' => 'Shop'],
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, '/media/deal.webp')]);
        (new PromoDealTilesCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(PromoDealTilesStruct::class, $data);
        self::assertSame('tile-0', $data->getTiles()[0]->getId());
    }

    public function testDuplicateIdsKeepFirstValidTile(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'tiles' => [
                [
                    'id' => 'sofas',
                    'label' => 'First',
                    'imageMedia' => self::MEDIA_ID,
                    'link' => ['url' => '/sale/first', 'label' => 'First'],
                ],
                [
                    'id' => 'sofas',
                    'label' => 'Second',
                    'imageMedia' => self::MEDIA_ID_2,
                    'link' => ['url' => '/sale/second', 'label' => 'Second'],
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, '/media/first.webp'),
            $this->media(self::MEDIA_ID_2, '/media/second.webp'),
        ]);

        (new PromoDealTilesCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(PromoDealTilesStruct::class, $data);
        self::assertCount(1, $data->getTiles());
        self::assertSame('First', $data->getTiles()[0]->getLabel());
    }

    public function testPartialTilesAreSkipped(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'tiles' => [
                [
                    'label' => '',
                    'imageMedia' => self::MEDIA_ID,
                    'link' => ['url' => '/broken'],
                ],
                [
                    'label' => 'Valid',
                    'imageMedia' => self::MEDIA_ID_2,
                    'link' => ['url' => '/valid', 'label' => 'Go'],
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, '/media/broken.webp'),
            $this->media(self::MEDIA_ID_2, '/media/valid.webp'),
        ]);

        (new PromoDealTilesCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(PromoDealTilesStruct::class, $data);
        self::assertCount(1, $data->getTiles());
        self::assertSame('Valid', $data->getTiles()[0]->getLabel());
    }

    public function testMissingMediaOmitsTile(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'tiles' => [[
                'label' => 'Sofas',
                'imageMedia' => self::MEDIA_ID,
                'link' => ['url' => '/sale/sofas', 'label' => 'Shop'],
            ]],
        ]);

        (new PromoDealTilesCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(PromoDealTilesStruct::class, $data);
        self::assertSame([], $data->getTiles());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testUnsafeLinkUrlOmitsTile(string $url): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'tiles' => [[
                'label' => 'Sofas',
                'imageMedia' => self::MEDIA_ID,
                'link' => ['url' => $url, 'label' => 'Shop'],
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, '/media/deal.webp')]);
        (new PromoDealTilesCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(PromoDealTilesStruct::class, $data);
        self::assertSame([], $data->getTiles());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'eyebrow' => '',
            'tiles' => [[
                'id' => 'sofas',
                'label' => 'Sofas',
                'description' => 'Deal',
                'discountLabel' => '-40 %',
                'endsAt' => '2026-09-14T23:59:59+02:00',
                'imageMedia' => self::MEDIA_ID,
                'link' => [
                    'label' => 'Explore',
                    'url' => '/sale/sofas',
                    'size' => 'medium',
                ],
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, '/media/deal.webp', 'Sofas')]);
        (new PromoDealTilesCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(PromoDealTilesStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_promo_deal_tiles', $payload['apiAlias']);
        self::assertNull($payload['eyebrow']);
        self::assertSame('cms_jv_promo_deal_tiles_tile', $payload['tiles'][0]['apiAlias']);
        self::assertSame('/sale/sofas', $payload['tiles'][0]['url']);
        self::assertSame('cms_jv_promo_deal_tiles_tile_media', $payload['tiles'][0]['image']['apiAlias']);
        self::assertSame('cms_jv_promo_deal_tiles_tile_link', $payload['tiles'][0]['link']['apiAlias']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_promo_deal_tiles_media_'.$slot->getUniqueIdentifier(),
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
     *     tiles?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('tiles', FieldConfig::SOURCE_STATIC, $values['tiles'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-promo-deal-tiles');
        $slot->setType(PromoDealTilesCmsElementResolver::TYPE);
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
