<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\TrendLookGridCmsElementResolver;
use Jv\Cms\DataResolver\Element\TrendLookGridStruct;
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

final class TrendLookGridCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string MEDIA_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new TrendLookGridCmsElementResolver();

        self::assertSame('jv-trend-look-grid', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new TrendLookGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrendLookGridStruct::class, $data);
        self::assertSame('cms_jv_trend_look_grid', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertSame([], $data->getCards());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'title' => '  Trend looks  ',
            'eyebrow' => '  This season  ',
            'cards' => [
                [
                    'id' => 'look-b',
                    'position' => 1,
                    'title' => '  Warm minimalism  ',
                    'description' => '  Soft tones  ',
                    'url' => '  /trends/warm  ',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'look-a',
                    'position' => 0,
                    'title' => 'Scandi calm',
                    'description' => '',
                    'url' => 'https://example.com/trends/scandi',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/warm.webp', 'Warm'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/scandi.webp', 'Scandi'),
        ]);

        (new TrendLookGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(TrendLookGridStruct::class, $data);
        self::assertSame('Trend looks', $data->getTitle());
        self::assertSame('This season', $data->getEyebrow());
        self::assertCount(2, $data->getCards());

        self::assertSame('look-a', $data->getCards()[0]->getId());
        self::assertSame(0, $data->getCards()[0]->getPosition());
        self::assertSame('Scandi calm', $data->getCards()[0]->getTitle());
        self::assertNull($data->getCards()[0]->getDescription());
        self::assertSame('cms_jv_trend_look_grid_card_media', $data->getCards()[0]->getImage()->getApiAlias());

        self::assertSame('look-b', $data->getCards()[1]->getId());
        self::assertSame('Warm minimalism', $data->getCards()[1]->getTitle());
        self::assertSame('Soft tones', $data->getCards()[1]->getDescription());
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot([
            'cards' => [[
                'title' => 'Look',
                'url' => '/trends/one',
                'imageMedia' => 'not-a-uuid',
            ]],
        ]);

        self::assertNull((new TrendLookGridCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectDedupesMediaIds(): void
    {
        $slot = $this->slot([
            'cards' => [
                [
                    'title' => 'First',
                    'url' => '/first',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Second',
                    'url' => '/second',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Third',
                    'url' => '/third',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $criteriaCollection = (new TrendLookGridCmsElementResolver())->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $named = $criteriaCollection->all()[MediaDefinition::class];
        $ids = $named['jv_trend_look_grid_media_'.$slot->getUniqueIdentifier()]->getIds();
        sort($ids);

        self::assertSame([self::MEDIA_ID, self::MEDIA_ID_2], $ids);
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
        (new TrendLookGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(TrendLookGridStruct::class, $data);
        self::assertSame([], $data->getCards());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['trends/warm'];
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_trend_look_grid_media_'.$slot->getUniqueIdentifier(),
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
        $slot->setUniqueIdentifier('slot-jv-trend-look-grid');
        $slot->setType(TrendLookGridCmsElementResolver::TYPE);
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
