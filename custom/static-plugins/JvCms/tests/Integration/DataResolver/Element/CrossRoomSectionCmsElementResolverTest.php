<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\CrossRoomSectionCmsElementResolver;
use Jv\Cms\DataResolver\Element\CrossRoomSectionRoomMediaStruct;
use Jv\Cms\DataResolver\Element\CrossRoomSectionRoomStruct;
use Jv\Cms\DataResolver\Element\CrossRoomSectionStruct;
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

final class CrossRoomSectionCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(CrossRoomSectionCmsElementResolver::class);
        self::assertInstanceOf(CrossRoomSectionCmsElementResolver::class, $resolver);
        self::assertSame('jv-cross-room-section', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'title' => '',
            'rooms' => [],
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
        self::assertSame('jv-cross-room-section', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(CrossRoomSectionStruct::class, $data);
        self::assertSame('cms_jv_cross_room_section', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertSame([], $data->getRooms());
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
            'rooms' => 'broken',
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
        self::assertInstanceOf(CrossRoomSectionStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_cross_room_section', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertSame([], $payload['rooms']);
    }

    public function testStructEncoderSerializesNonEmptyRooms(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $livingImage = new CrossRoomSectionRoomMediaStruct('https://cdn.example.com/living.webp', 'Living room');
        $diningImage = new CrossRoomSectionRoomMediaStruct('https://cdn.example.com/dining.webp', 'Dining room');

        $data = new CrossRoomSectionStruct(
            title: 'Explore every room',
            eyebrow: 'Shop by room',
            rooms: [
                new CrossRoomSectionRoomStruct(
                    id: 'dining-room',
                    position: 0,
                    label: 'Dining',
                    title: 'Tables and chairs',
                    description: 'Gather around.',
                    url: '/dining',
                    image: $diningImage,
                ),
                new CrossRoomSectionRoomStruct(
                    id: 'living-room',
                    position: 1,
                    label: 'Living room',
                    title: 'Sofas and tables',
                    description: null,
                    url: '/living',
                    image: $livingImage,
                ),
            ],
        );

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_cross_room_section', $payload['apiAlias']);
        self::assertSame('Explore every room', $payload['title']);
        self::assertSame('Shop by room', $payload['eyebrow']);
        self::assertCount(2, $payload['rooms']);
        self::assertSame('cms_jv_cross_room_section_room', $payload['rooms'][0]['apiAlias']);
        self::assertSame('dining-room', $payload['rooms'][0]['id']);
        self::assertSame('Gather around.', $payload['rooms'][0]['description']);
        self::assertSame('cms_jv_cross_room_section_room_media', $payload['rooms'][0]['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/dining.webp', $payload['rooms'][0]['image']['url']);
        self::assertSame('cms_jv_cross_room_section_room', $payload['rooms'][1]['apiAlias']);
        self::assertSame('living-room', $payload['rooms'][1]['id']);
        self::assertNull($payload['rooms'][1]['description']);
        self::assertSame('/living', $payload['rooms'][1]['url']);
    }

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string|null,
     *     rooms?: mixed
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $config->add(new FieldConfig('rooms', FieldConfig::SOURCE_STATIC, $values['rooms'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-cross-room-section-integration');
        $slot->setType(CrossRoomSectionCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
