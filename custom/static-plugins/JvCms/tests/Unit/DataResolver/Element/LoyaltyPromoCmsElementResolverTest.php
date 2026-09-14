<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\LoyaltyPromoCmsElementResolver;
use Jv\Cms\DataResolver\Element\LoyaltyPromoStruct;
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

final class LoyaltyPromoCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new LoyaltyPromoCmsElementResolver();

        self::assertSame('jv-loyalty-promo', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => 'not-a-uuid']);

        self::assertNull((new LoyaltyPromoCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => self::MEDIA_ID]);

        $criteriaCollection = (new LoyaltyPromoCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $named = $criteriaCollection->all()[MediaDefinition::class];
        self::assertArrayHasKey('jv_loyalty_promo_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_loyalty_promo_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new LoyaltyPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(LoyaltyPromoStruct::class, $data);
        self::assertSame('cms_jv_loyalty_promo', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertSame('', $data->getDescription());
        self::assertSame([], $data->getBenefits());
        self::assertSame('', $data->getPromoCode());
        self::assertNull($data->getImage());
        self::assertNull($data->getLink());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'title' => '  Loyalty club  ',
            'description' => '  Earn points.  ',
            'benefits' => [
                [
                    'id' => 'points',
                    'position' => 1,
                    'text' => '  Earn points  ',
                ],
                [
                    'id' => 'discount',
                    'position' => 0,
                    'text' => 'Exclusive discounts',
                ],
            ],
            'promoCode' => '  LOYAL10  ',
            'imageMedia' => self::MEDIA_ID,
            'link' => [
                'label' => '  Join now  ',
                'url' => '  /loyalty  ',
                'size' => 'large',
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/loyalty.webp', 'Loyalty')]);
        (new LoyaltyPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(LoyaltyPromoStruct::class, $data);
        self::assertSame('Loyalty club', $data->getTitle());
        self::assertSame('Earn points.', $data->getDescription());
        self::assertSame('LOYAL10', $data->getPromoCode());
        self::assertCount(2, $data->getBenefits());
        self::assertSame('discount', $data->getBenefits()[0]->getId());
        self::assertSame(0, $data->getBenefits()[0]->getPosition());
        self::assertSame('points', $data->getBenefits()[1]->getId());

        $image = $data->getImage();
        self::assertNotNull($image);
        self::assertSame('cms_jv_loyalty_promo_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/loyalty.webp', $image->getUrl());

        $link = $data->getLink();
        self::assertNotNull($link);
        self::assertSame('cms_jv_loyalty_promo_link', $link->getApiAlias());
        self::assertSame('Join now', $link->getLabel());
        self::assertSame('/loyalty', $link->getUrl());
        self::assertSame('large', $link->getSize());
    }

    public function testEmptyBenefitTextIsSkipped(): void
    {
        $slot = $this->slot([
            'benefits' => [
                ['text' => ''],
                ['text' => 'Valid benefit'],
            ],
        ]);

        (new LoyaltyPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(LoyaltyPromoStruct::class, $data);
        self::assertCount(1, $data->getBenefits());
        self::assertSame('Valid benefit', $data->getBenefits()[0]->getText());
    }

    public function testDuplicateBenefitIdsKeepFirst(): void
    {
        $slot = $this->slot([
            'benefits' => [
                ['id' => 'same', 'text' => 'First'],
                ['id' => 'same', 'text' => 'Second'],
            ],
        ]);

        (new LoyaltyPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(LoyaltyPromoStruct::class, $data);
        self::assertCount(1, $data->getBenefits());
        self::assertSame('First', $data->getBenefits()[0]->getText());
    }

    public function testEmptyBenefitIdFallsBackToIndex(): void
    {
        $slot = $this->slot([
            'benefits' => [
                ['text' => 'Benefit one'],
            ],
        ]);

        (new LoyaltyPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(LoyaltyPromoStruct::class, $data);
        self::assertSame('benefit-0', $data->getBenefits()[0]->getId());
    }

    public function testUnknownLinkSizeDefaultsToMedium(): void
    {
        $slot = $this->slot([
            'link' => [
                'label' => 'Join',
                'url' => '/loyalty',
                'size' => 'xl',
            ],
        ]);

        (new LoyaltyPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(LoyaltyPromoStruct::class, $data);
        self::assertNotNull($data->getLink());
        self::assertSame('medium', $data->getLink()->getSize());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testUnsafeLinkUrlBecomesNull(string $url): void
    {
        $slot = $this->slot([
            'link' => [
                'label' => 'Join',
                'url' => $url,
            ],
        ]);

        (new LoyaltyPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(LoyaltyPromoStruct::class, $data);
        self::assertNull($data->getLink());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['loyalty'];
    }

    public function testNonArrayBenefitsYieldsEmptyList(): void
    {
        $slot = $this->slot(['benefits' => 'broken']);
        (new LoyaltyPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(LoyaltyPromoStruct::class, $data);
        self::assertSame([], $data->getBenefits());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Loyalty',
            'benefits' => [['id' => 'points', 'text' => 'Earn points']],
            'link' => [
                'label' => 'Join',
                'url' => '/loyalty',
                'size' => 'small',
            ],
        ]);

        (new LoyaltyPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(LoyaltyPromoStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_loyalty_promo', $payload['apiAlias']);
        self::assertSame('cms_jv_loyalty_promo_benefit', $payload['benefits'][0]['apiAlias']);
        self::assertSame('cms_jv_loyalty_promo_link', $payload['link']['apiAlias']);
        self::assertSame('small', $payload['link']['size']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_loyalty_promo_media_'.$slot->getUniqueIdentifier(),
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
            $media->setFileName('loyalty.webp');
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
     *     benefits?: mixed,
     *     promoCode?: string,
     *     imageMedia?: string,
     *     link?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('benefits', FieldConfig::SOURCE_STATIC, $values['benefits'] ?? []));
        $collection->add(new FieldConfig('promoCode', FieldConfig::SOURCE_STATIC, $values['promoCode'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? ''));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
            'size' => 'medium',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-loyalty-promo');
        $slot->setType(LoyaltyPromoCmsElementResolver::TYPE);
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
