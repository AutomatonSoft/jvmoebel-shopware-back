<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\TrustRatingCmsElementResolver;
use Jv\Cms\DataResolver\Element\TrustRatingLinkStruct;
use Jv\Cms\DataResolver\Element\TrustRatingStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class TrustRatingCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(TrustRatingCmsElementResolver::class);
        self::assertInstanceOf(TrustRatingCmsElementResolver::class, $resolver);
        self::assertSame('jv-trust-rating', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'rating' => 4.8,
            'reviewCount' => 256,
            'providerLabel' => 'Trusted Shops',
            'link' => [
                'label' => 'Reviews',
                'url' => '/reviews',
            ],
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-trust-rating', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);
        self::assertSame('cms_jv_trust_rating', $data->getApiAlias());
        self::assertSame(4.8, $data->getRating());
        self::assertSame(256, $data->getReviewCount());
        self::assertSame('Trusted Shops', $data->getProviderLabel());
        self::assertNotNull($data->getLink());
        self::assertSame('/reviews', $data->getLink()->getUrl());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'rating' => 6.0,
            'reviewCount' => -5,
            'providerLabel' => '',
            'link' => [
                'label' => 'Reviews',
                'url' => 'javascript:alert(1)',
            ],
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_trust_rating', $payload['apiAlias']);
        self::assertNull($payload['rating']);
        self::assertNull($payload['reviewCount']);
        self::assertSame('', $payload['providerLabel']);
        self::assertNull($payload['link']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new TrustRatingStruct(
            rating: 4.5,
            reviewCount: 100,
            providerLabel: 'Trusted Shops',
            link: new TrustRatingLinkStruct('Read reviews', 'https://example.com/reviews'),
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_trust_rating', $payload['apiAlias']);
        self::assertSame(4.5, $payload['rating']);
        self::assertSame(100, $payload['reviewCount']);
        self::assertSame('Trusted Shops', $payload['providerLabel']);
        self::assertSame('cms_jv_trust_rating_link', $payload['link']['apiAlias']);
        self::assertSame('Read reviews', $payload['link']['label']);
        self::assertSame('https://example.com/reviews', $payload['link']['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('rating', FieldConfig::SOURCE_STATIC, $values['rating'] ?? null));
        $collection->add(new FieldConfig('reviewCount', FieldConfig::SOURCE_STATIC, $values['reviewCount'] ?? null));
        $collection->add(new FieldConfig('providerLabel', FieldConfig::SOURCE_STATIC, $values['providerLabel'] ?? ''));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-trust-rating');
        $slot->setType(TrustRatingCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
