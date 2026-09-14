<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\PromoDealTileLinkStruct;
use Jv\Cms\DataResolver\Element\PromoDealTileMediaStruct;
use Jv\Cms\DataResolver\Element\PromoDealTilesCmsElementResolver;
use Jv\Cms\DataResolver\Element\PromoDealTilesStruct;
use Jv\Cms\DataResolver\Element\PromoDealTileStruct;
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

final class PromoDealTilesCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(PromoDealTilesCmsElementResolver::class);
        self::assertInstanceOf(PromoDealTilesCmsElementResolver::class, $resolver);
        self::assertSame('jv-promo-deal-tiles', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'title' => '',
            'eyebrow' => '',
            'tiles' => [],
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
        self::assertSame('jv-promo-deal-tiles', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(PromoDealTilesStruct::class, $data);
        self::assertSame('cms_jv_promo_deal_tiles', $data->getApiAlias());
        self::assertSame([], $data->getTiles());
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
            'tiles' => [
                [
                    'label' => 'Broken',
                    'imageMedia' => 'not-a-uuid',
                    'link' => ['url' => 'javascript:alert(1)', 'label' => 'Go'],
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
        self::assertInstanceOf(PromoDealTilesStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_promo_deal_tiles', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertSame([], $payload['tiles']);
    }

    public function testStructEncoderSerializesNonEmptyTile(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new PromoDealTilesStruct(
            title: 'Homie Days',
            eyebrow: 'Until Sunday',
            tiles: [
                new PromoDealTileStruct(
                    id: 'sofas',
                    position: 0,
                    label: 'Sofas',
                    description: 'Up to 40%',
                    discountLabel: '-40 %',
                    endsAt: '2026-09-14T23:59:59+02:00',
                    url: '/sale/sofas',
                    image: new PromoDealTileMediaStruct('/media/deal.webp', 'Sofas'),
                    link: new PromoDealTileLinkStruct('Explore', '/sale/sofas', 'medium'),
                ),
            ],
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_promo_deal_tiles', $payload['apiAlias']);
        self::assertSame('cms_jv_promo_deal_tiles_tile', $payload['tiles'][0]['apiAlias']);
        self::assertSame('/sale/sofas', $payload['tiles'][0]['url']);
        self::assertSame('cms_jv_promo_deal_tiles_tile_media', $payload['tiles'][0]['image']['apiAlias']);
        self::assertSame('cms_jv_promo_deal_tiles_tile_link', $payload['tiles'][0]['link']['apiAlias']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('tiles', FieldConfig::SOURCE_STATIC, $values['tiles'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-promo-deal-tiles');
        $slot->setType(PromoDealTilesCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
