<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ChipRailChipStruct;
use Jv\Cms\DataResolver\Element\ChipRailCmsElementResolver;
use Jv\Cms\DataResolver\Element\ChipRailStruct;
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

final class ChipRailCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(ChipRailCmsElementResolver::class);
        self::assertInstanceOf(ChipRailCmsElementResolver::class, $resolver);
        self::assertSame('jv-chip-rail', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-chip-rail', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(ChipRailStruct::class, $data);
        self::assertSame('cms_jv_chip_rail', $data->getApiAlias());
        self::assertSame([], $data->getChips());
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
            'chips' => [
                [
                    'label' => 'Broken',
                    'url' => 'javascript:alert(1)',
                ],
                [
                    'label' => '',
                    'url' => '/valid',
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
        self::assertInstanceOf(ChipRailStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_chip_rail', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertSame([], $payload['chips']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new ChipRailStruct(
            title: 'Shop by style',
            eyebrow: 'Browse',
            chips: [
                new ChipRailChipStruct(
                    id: 'coastal',
                    position: 0,
                    label: 'Coastal',
                    url: '/styles/coastal',
                ),
            ],
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_chip_rail', $payload['apiAlias']);
        self::assertSame('Shop by style', $payload['title']);
        self::assertSame('Browse', $payload['eyebrow']);
        self::assertSame('cms_jv_chip_rail_chip', $payload['chips'][0]['apiAlias']);
        self::assertSame('Coastal', $payload['chips'][0]['label']);
        self::assertSame('/styles/coastal', $payload['chips'][0]['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('chips', FieldConfig::SOURCE_STATIC, $values['chips'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-chip-rail');
        $slot->setType(ChipRailCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
