<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\OfferRailCmsElementResolver;
use Jv\Cms\DataResolver\Element\OfferRailMediaStruct;
use Jv\Cms\DataResolver\Element\OfferRailOfferStruct;
use Jv\Cms\DataResolver\Element\OfferRailStruct;
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

final class OfferRailCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(OfferRailCmsElementResolver::class);
        self::assertInstanceOf(OfferRailCmsElementResolver::class, $resolver);
        self::assertSame('jv-offer-rail', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'title' => '',
            'offers' => [],
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
        self::assertSame('jv-offer-rail', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(OfferRailStruct::class, $data);
        self::assertSame('cms_jv_offer_rail', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertNull($data->getAriaLabel());
        self::assertSame([], $data->getOffers());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'eyebrow' => '  ',
            'description' => null,
            'ariaLabel' => '',
            'offers' => [
                [
                    'id' => 'broken-offer',
                    'title' => 'Sofas',
                    'ctaLabel' => 'Shop',
                    'url' => 'javascript:alert(1)',
                    'endsAt' => 'not-a-date',
                    'imageMedia' => 'not-a-uuid',
                ],
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

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(OfferRailStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_offer_rail', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertNull($payload['ariaLabel']);
        self::assertSame([], $payload['offers']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new OfferRailStruct(
            title: 'Current offers',
            eyebrow: 'Deals',
            description: 'Save on selected ranges',
            ariaLabel: 'Promotional offers carousel',
            offers: [
                new OfferRailOfferStruct(
                    id: 'sofas',
                    position: 0,
                    title: 'Sofas',
                    subtitle: 'Up to 40% off',
                    ctaLabel: 'Shop sofas',
                    url: '/sale/sofas',
                    endsAt: '2026-09-14T23:59:59+02:00',
                    legalText: 'While stocks last',
                    image: new OfferRailMediaStruct('https://cdn.example.com/sofas.webp', 'Sofas offer'),
                ),
            ],
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_offer_rail', $payload['apiAlias']);
        self::assertSame('Current offers', $payload['title']);
        self::assertSame('Deals', $payload['eyebrow']);
        self::assertSame('Save on selected ranges', $payload['description']);
        self::assertSame('Promotional offers carousel', $payload['ariaLabel']);
        self::assertCount(1, $payload['offers']);
        self::assertSame('cms_jv_offer_rail_offer', $payload['offers'][0]['apiAlias']);
        self::assertSame('sofas', $payload['offers'][0]['id']);
        self::assertSame('/sale/sofas', $payload['offers'][0]['url']);
        self::assertSame('2026-09-14T23:59:59+02:00', $payload['offers'][0]['endsAt']);
        self::assertSame('cms_jv_offer_rail_media', $payload['offers'][0]['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/sofas.webp', $payload['offers'][0]['image']['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('ariaLabel', FieldConfig::SOURCE_STATIC, $values['ariaLabel'] ?? ''));
        $collection->add(new FieldConfig('offers', FieldConfig::SOURCE_STATIC, $values['offers'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-offer-rail');
        $slot->setType(OfferRailCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
