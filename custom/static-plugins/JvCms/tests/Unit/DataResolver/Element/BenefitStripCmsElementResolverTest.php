<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\BenefitStripCmsElementResolver;
use Jv\Cms\DataResolver\Element\BenefitStripStruct;
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

final class BenefitStripCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string MEDIA_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new BenefitStripCmsElementResolver();

        self::assertSame('jv-benefit-strip', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot([
            'items' => [[
                'title' => 'Free delivery',
                'description' => 'On orders over 100 EUR',
                'iconMedia' => 'not-a-uuid',
            ]],
        ]);

        self::assertNull((new BenefitStripCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot([
            'items' => [[
                'title' => 'Free delivery',
                'description' => 'On orders over 100 EUR',
                'iconMedia' => self::MEDIA_ID,
            ]],
        ]);

        $criteriaCollection = (new BenefitStripCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_benefit_strip_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_benefit_strip_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testCollectDedupesMediaIds(): void
    {
        $slot = $this->slot([
            'items' => [
                [
                    'title' => 'Free delivery',
                    'description' => 'On orders over 100 EUR',
                    'iconMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Easy returns',
                    'description' => '30-day return policy',
                    'iconMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Expert advice',
                    'description' => 'Talk to our team',
                    'iconMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $criteriaCollection = (new BenefitStripCmsElementResolver())->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $named = $criteriaCollection->all()[MediaDefinition::class];
        $ids = $named['jv_benefit_strip_media_'.$slot->getUniqueIdentifier()]->getIds();
        sort($ids);

        self::assertSame([self::MEDIA_ID, self::MEDIA_ID_2], $ids);
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new BenefitStripCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(BenefitStripStruct::class, $data);
        self::assertSame('cms_jv_benefit_strip', $data->getApiAlias());
        self::assertSame([], $data->getItems());
    }

    public function testItNormalizesHappyPathWithOneItem(): void
    {
        $slot = $this->slot([
            'items' => [[
                'id' => 'free-delivery',
                'position' => 1,
                'title' => '  Free delivery  ',
                'description' => '  On orders over 100 EUR  ',
                'iconMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/delivery.svg', 'Delivery icon')]);
        (new BenefitStripCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(BenefitStripStruct::class, $data);
        self::assertCount(1, $data->getItems());

        $item = $data->getItems()[0];
        self::assertSame('free-delivery', $item->getId());
        self::assertSame(1, $item->getPosition());
        self::assertSame('Free delivery', $item->getTitle());
        self::assertSame('On orders over 100 EUR', $item->getDescription());

        $icon = $item->getIcon();
        self::assertSame('cms_jv_benefit_strip_media', $icon->getApiAlias());
        self::assertSame('https://cdn.example.com/delivery.svg', $icon->getUrl());
        self::assertSame('Delivery icon', $icon->getAlt());
    }

    public function testInvalidMediaUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->slot([
            'items' => [[
                'title' => 'Free delivery',
                'description' => 'On orders over 100 EUR',
                'iconMedia' => 'not-a-uuid',
            ]],
        ]);

        (new BenefitStripCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(BenefitStripStruct::class, $data);
        self::assertSame([], $data->getItems());
    }

    public function testValidMediaUuidMissingFromResultOmitsItem(): void
    {
        $slot = $this->slot([
            'items' => [[
                'title' => 'Free delivery',
                'description' => 'On orders over 100 EUR',
                'iconMedia' => self::MEDIA_ID,
            ]],
        ]);

        (new BenefitStripCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(BenefitStripStruct::class, $data);
        self::assertSame([], $data->getItems());
    }

    public function testDuplicateIdsKeepFirstValidItem(): void
    {
        $slot = $this->slot([
            'items' => [
                [
                    'id' => 'free-delivery',
                    'title' => 'First',
                    'description' => 'First description',
                    'iconMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'free-delivery',
                    'title' => 'Second',
                    'description' => 'Second description',
                    'iconMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/first.svg'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/second.svg'),
        ]);
        (new BenefitStripCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(BenefitStripStruct::class, $data);
        self::assertCount(1, $data->getItems());
        self::assertSame('First', $data->getItems()[0]->getTitle());
    }

    public function testPartialItemsAreSkipped(): void
    {
        $slot = $this->slot([
            'items' => [
                [
                    'title' => '',
                    'description' => 'Missing title',
                    'iconMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Easy returns',
                    'description' => '30-day return policy',
                    'iconMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/broken.svg'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/returns.svg'),
        ]);
        (new BenefitStripCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(BenefitStripStruct::class, $data);
        self::assertCount(1, $data->getItems());
        self::assertSame('Easy returns', $data->getItems()[0]->getTitle());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'items' => [[
                'id' => 'free-delivery',
                'title' => 'Free delivery',
                'description' => 'On orders over 100 EUR',
                'iconMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/delivery.svg', 'Delivery')]);
        (new BenefitStripCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(BenefitStripStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_benefit_strip', $payload['apiAlias']);
        self::assertIsArray($payload['items']);
        self::assertSame('cms_jv_benefit_strip_item', $payload['items'][0]['apiAlias']);
        self::assertSame('cms_jv_benefit_strip_media', $payload['items'][0]['icon']['apiAlias']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_benefit_strip_media_'.$slot->getUniqueIdentifier(),
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
     *     items?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('items', FieldConfig::SOURCE_STATIC, $values['items'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-benefit-strip');
        $slot->setType(BenefitStripCmsElementResolver::TYPE);
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
