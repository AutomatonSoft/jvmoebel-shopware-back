<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\OfferRailCmsElementResolver;
use Jv\Cms\DataResolver\Element\OfferRailStruct;
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

final class OfferRailCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string MEDIA_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new OfferRailCmsElementResolver();

        self::assertSame('jv-offer-rail', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot([
            'offers' => [[
                'title' => 'Sofa sale',
                'ctaLabel' => 'Shop',
                'url' => '/sale',
                'imageMedia' => 'not-a-uuid',
            ]],
        ]);

        self::assertNull((new OfferRailCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot([
            'offers' => [[
                'title' => 'Sofa sale',
                'ctaLabel' => 'Shop',
                'url' => '/sale',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $criteriaCollection = (new OfferRailCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_offer_rail_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_offer_rail_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testCollectDedupesMediaIds(): void
    {
        $slot = $this->slot([
            'offers' => [
                [
                    'title' => 'Offer A',
                    'ctaLabel' => 'Shop A',
                    'url' => '/a',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Offer B',
                    'ctaLabel' => 'Shop B',
                    'url' => '/b',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Offer C',
                    'ctaLabel' => 'Shop C',
                    'url' => '/c',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $criteriaCollection = (new OfferRailCmsElementResolver())->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $named = $criteriaCollection->all()[MediaDefinition::class];
        $ids = $named['jv_offer_rail_media_'.$slot->getUniqueIdentifier()]->getIds();
        sort($ids);

        self::assertSame([self::MEDIA_ID, self::MEDIA_ID_2], $ids);
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new OfferRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(OfferRailStruct::class, $data);
        self::assertSame('cms_jv_offer_rail', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertNull($data->getAriaLabel());
        self::assertSame([], $data->getOffers());
    }

    public function testItNormalizesHappyPathWithOneOffer(): void
    {
        $slot = $this->slot([
            'title' => '  Top offers  ',
            'eyebrow' => '  Deals  ',
            'description' => '  Save today  ',
            'ariaLabel' => '  Offer carousel  ',
            'offers' => [[
                'id' => 'sofa-sale',
                'position' => 1,
                'title' => '  Sofa sale  ',
                'subtitle' => '  Up to 20 %  ',
                'ctaLabel' => '  Shop sofas  ',
                'url' => '  /sofas  ',
                'endsAt' => '  2026-09-15T23:59:59+02:00  ',
                'legalText' => '  Terms apply  ',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/sofa.webp', 'Sofa offer')]);
        (new OfferRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(OfferRailStruct::class, $data);
        self::assertSame('Top offers', $data->getTitle());
        self::assertSame('Deals', $data->getEyebrow());
        self::assertSame('Save today', $data->getDescription());
        self::assertSame('Offer carousel', $data->getAriaLabel());
        self::assertCount(1, $data->getOffers());

        $offer = $data->getOffers()[0];
        self::assertSame('sofa-sale', $offer->getId());
        self::assertSame(1, $offer->getPosition());
        self::assertSame('Sofa sale', $offer->getTitle());
        self::assertSame('Up to 20 %', $offer->getSubtitle());
        self::assertSame('Shop sofas', $offer->getCtaLabel());
        self::assertSame('/sofas', $offer->getUrl());
        self::assertSame('2026-09-15T23:59:59+02:00', $offer->getEndsAt());
        self::assertSame('Terms apply', $offer->getLegalText());

        $image = $offer->getImage();
        self::assertSame('cms_jv_offer_rail_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/sofa.webp', $image->getUrl());
        self::assertSame('Sofa offer', $image->getAlt());
    }

    public function testInvalidEndsAtBecomesNull(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'offers' => [[
                'id' => 'offer-1',
                'title' => 'Sofa sale',
                'ctaLabel' => 'Shop',
                'url' => '/sale',
                'endsAt' => 'not-a-date',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/sofa.webp')]);
        (new OfferRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(OfferRailStruct::class, $data);
        self::assertCount(1, $data->getOffers());
        self::assertNull($data->getOffers()[0]->getEndsAt());
    }

    public function testDuplicateIdsKeepFirstValidOffer(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'offers' => [
                [
                    'id' => 'sofa-sale',
                    'title' => 'First',
                    'ctaLabel' => 'Shop',
                    'url' => '/first',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'sofa-sale',
                    'title' => 'Second',
                    'ctaLabel' => 'Shop',
                    'url' => '/second',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/first.webp'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/second.webp'),
        ]);
        (new OfferRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(OfferRailStruct::class, $data);
        self::assertCount(1, $data->getOffers());
        self::assertSame('First', $data->getOffers()[0]->getTitle());
    }

    public function testPartialOffersAreSkipped(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'offers' => [
                [
                    'title' => '',
                    'ctaLabel' => 'Shop',
                    'url' => '/broken',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'title' => 'Valid offer',
                    'ctaLabel' => 'Shop',
                    'url' => '/valid',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/broken.webp'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/valid.webp'),
        ]);
        (new OfferRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(OfferRailStruct::class, $data);
        self::assertCount(1, $data->getOffers());
        self::assertSame('Valid offer', $data->getOffers()[0]->getTitle());
    }

    public function testValidMediaUuidMissingFromResultOmitsOffer(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'offers' => [[
                'title' => 'Sofa sale',
                'ctaLabel' => 'Shop',
                'url' => '/sale',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        (new OfferRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(OfferRailStruct::class, $data);
        self::assertSame([], $data->getOffers());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'eyebrow' => '',
            'description' => '  ',
            'ariaLabel' => '',
            'offers' => [[
                'id' => 'offer-1',
                'title' => 'Sofa sale',
                'ctaLabel' => 'Shop',
                'url' => '/sale',
                'endsAt' => '2026-09-15T23:59:59+02:00',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/sofa.webp', 'Sofa')]);
        (new OfferRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(OfferRailStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_offer_rail', $payload['apiAlias']);
        self::assertSame('Title', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertNull($payload['ariaLabel']);
        self::assertIsArray($payload['offers']);
        self::assertSame('cms_jv_offer_rail_offer', $payload['offers'][0]['apiAlias']);
        self::assertSame('cms_jv_offer_rail_media', $payload['offers'][0]['image']['apiAlias']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_offer_rail_media_'.$slot->getUniqueIdentifier(),
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
     *     ariaLabel?: string,
     *     offers?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('ariaLabel', FieldConfig::SOURCE_STATIC, $values['ariaLabel'] ?? ''));
        $collection->add(new FieldConfig('offers', FieldConfig::SOURCE_STATIC, $values['offers'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-offer-rail');
        $slot->setType(OfferRailCmsElementResolver::TYPE);
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
