<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\RelatedLookCardsCmsElementResolver;
use Jv\Cms\DataResolver\Element\RelatedLookCardsStruct;
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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class RelatedLookCardsCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string MEDIA_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new RelatedLookCardsCmsElementResolver();

        self::assertSame('jv-related-look-cards', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new RelatedLookCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(RelatedLookCardsStruct::class, $data);
        self::assertSame('cms_jv_related_look_cards', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertSame([], $data->getCards());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'title' => '  More looks  ',
            'cards' => [
                [
                    'id' => 'look-b',
                    'position' => 1,
                    'title' => '  Coastal living  ',
                    'description' => '  Light and airy  ',
                    'url' => '  /looks/coastal  ',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'look-a',
                    'position' => 0,
                    'title' => 'Urban loft',
                    'description' => '',
                    'url' => 'https://example.com/looks/urban',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/coastal.webp', 'Coastal'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/urban.webp', 'Urban'),
        ]);

        (new RelatedLookCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RelatedLookCardsStruct::class, $data);
        self::assertSame('More looks', $data->getTitle());
        self::assertCount(2, $data->getCards());

        self::assertSame('look-a', $data->getCards()[0]->getId());
        self::assertSame(0, $data->getCards()[0]->getPosition());
        self::assertSame('Urban loft', $data->getCards()[0]->getTitle());
        self::assertNull($data->getCards()[0]->getDescription());
        self::assertSame('https://example.com/looks/urban', $data->getCards()[0]->getUrl());
        self::assertSame('cms_jv_related_look_cards_card_media', $data->getCards()[0]->getImage()->getApiAlias());

        self::assertSame('look-b', $data->getCards()[1]->getId());
        self::assertSame('Coastal living', $data->getCards()[1]->getTitle());
        self::assertSame('Light and airy', $data->getCards()[1]->getDescription());
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot([
            'cards' => [[
                'title' => 'Look',
                'url' => '/looks/one',
                'imageMedia' => 'not-a-uuid',
            ]],
        ]);

        self::assertNull((new RelatedLookCardsCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot([
            'cards' => [[
                'title' => 'Look',
                'url' => '/looks/one',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $criteriaCollection = (new RelatedLookCardsCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $named = $criteriaCollection->all()[MediaDefinition::class];
        self::assertArrayHasKey('jv_related_look_cards_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_related_look_cards_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testDuplicateIdsKeepFirstValidCard(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'cards' => [
                [
                    'id' => 'same-id',
                    'title' => 'First',
                    'url' => '/first',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'same-id',
                    'title' => 'Second',
                    'url' => '/second',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/first.webp'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/second.webp'),
        ]);

        (new RelatedLookCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RelatedLookCardsStruct::class, $data);
        self::assertCount(1, $data->getCards());
        self::assertSame('First', $data->getCards()[0]->getTitle());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'cards' => [[
                'title' => 'Look',
                'url' => $url,
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/look.webp')]);
        (new RelatedLookCardsCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(RelatedLookCardsStruct::class, $data);
        self::assertSame([], $data->getCards());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['looks/coastal'];
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_related_look_cards_media_'.$slot->getUniqueIdentifier(),
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
     * @param array{
     *     title?: string,
     *     cards?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('cards', FieldConfig::SOURCE_STATIC, $values['cards'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-related-look-cards');
        $slot->setType(RelatedLookCardsCmsElementResolver::TYPE);
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
