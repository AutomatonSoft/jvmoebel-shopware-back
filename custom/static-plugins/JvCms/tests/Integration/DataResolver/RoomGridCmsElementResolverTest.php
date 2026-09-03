<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\RoomGridCmsElementResolver;
use Jv\Cms\DataResolver\Element\RoomGridMediaStruct;
use Jv\Cms\DataResolver\Element\RoomGridRoomStruct;
use Jv\Cms\DataResolver\Element\RoomGridStruct;
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

final class RoomGridCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(RoomGridCmsElementResolver::class);
        self::assertInstanceOf(RoomGridCmsElementResolver::class, $resolver);
        self::assertSame('jv-room-grid', $resolver->getType());

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
        self::assertSame('jv-room-grid', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(RoomGridStruct::class, $data);
        self::assertSame('cms_jv_room_grid', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
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
            'description' => null,
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
        self::assertInstanceOf(RoomGridStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_room_grid', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertSame([], $payload['rooms']);
    }

    public function testStructEncoderSerializesNonEmptyRooms(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $livingImage = new RoomGridMediaStruct('https://cdn.example.com/living.webp', 'Living room');
        $diningImage = new RoomGridMediaStruct('https://cdn.example.com/dining.webp', 'Dining room');

        $data = new RoomGridStruct(
            title: 'Furniture for every room.',
            eyebrow: 'Shop by room',
            description: 'Discover pieces selected to work together.',
            rooms: [
                new RoomGridRoomStruct(
                    id: 'dining-room',
                    position: 0,
                    featured: false,
                    label: 'Dining',
                    title: 'Tables, chairs and storage',
                    url: '/dining',
                    image: $diningImage,
                ),
                new RoomGridRoomStruct(
                    id: 'living-room',
                    position: 1,
                    featured: true,
                    label: 'Living room',
                    title: 'Sofas, armchairs and tables',
                    url: '/living',
                    image: $livingImage,
                ),
            ],
        );

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_room_grid', $payload['apiAlias']);
        self::assertSame('Furniture for every room.', $payload['title']);
        self::assertSame('Shop by room', $payload['eyebrow']);
        self::assertSame('Discover pieces selected to work together.', $payload['description']);
        self::assertCount(2, $payload['rooms']);
        self::assertSame('cms_jv_room_grid_room', $payload['rooms'][0]['apiAlias']);
        self::assertSame('dining-room', $payload['rooms'][0]['id']);
        self::assertFalse($payload['rooms'][0]['featured']);
        self::assertSame('cms_jv_room_grid_media', $payload['rooms'][0]['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/dining.webp', $payload['rooms'][0]['image']['url']);
        self::assertSame('cms_jv_room_grid_room', $payload['rooms'][1]['apiAlias']);
        self::assertSame('living-room', $payload['rooms'][1]['id']);
        self::assertTrue($payload['rooms'][1]['featured']);
        self::assertSame('/living', $payload['rooms'][1]['url']);
    }

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string|null,
     *     description?: string|null,
     *     rooms?: mixed
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $config->add(new FieldConfig('rooms', FieldConfig::SOURCE_STATIC, $values['rooms'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-room-grid-integration');
        $slot->setType(RoomGridCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
