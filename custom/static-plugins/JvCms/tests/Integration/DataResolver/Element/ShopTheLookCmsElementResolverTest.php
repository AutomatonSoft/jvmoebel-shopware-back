<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ShopTheLookCmsElementResolver;
use Jv\Cms\DataResolver\Element\ShopTheLookItemStruct;
use Jv\Cms\DataResolver\Element\ShopTheLookLinkStruct;
use Jv\Cms\DataResolver\Element\ShopTheLookMediaStruct;
use Jv\Cms\DataResolver\Element\ShopTheLookStruct;
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

final class ShopTheLookCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();
        $resolver = $container->get(ShopTheLookCmsElementResolver::class);
        self::assertInstanceOf(ShopTheLookCmsElementResolver::class, $resolver);
        self::assertSame('jv-shop-the-look', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);
        $slot = $this->createSlot(['items' => 'broken']);
        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext($this->createMock(SalesChannelContext::class), new Request()),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        $data = $resolvedSlot->getData();
        self::assertInstanceOf(ShopTheLookStruct::class, $data);
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getImage());
        self::assertSame([], $data->getItems());
    }

    public function testStoreApiEncoderSerializesEmptyContract(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);
        $payload = $encoder->encode(
            new ShopTheLookStruct('', null, null, null, [], null),
            new ResponseFields(),
        );

        self::assertSame('cms_jv_shop_the_look', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertNull($payload['image']);
        self::assertSame([], $payload['items']);
        self::assertNull($payload['viewAll']);
    }

    public function testStoreApiEncoderSerializesFrontendContract(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);
        $data = new ShopTheLookStruct(
            title: 'Bring this look home.',
            eyebrow: 'One room, one look',
            description: 'Discover the furniture in this room.',
            image: new ShopTheLookMediaStruct('https://cdn.example.com/look.webp', 'Living room'),
            items: [
                new ShopTheLookItemStruct(
                    id: 'sofa',
                    position: 0,
                    name: 'Alba Modular Sofa',
                    description: 'Natural bouclé',
                    url: '/product/alba',
                    hotspot: ['x' => 69.0, 'y' => 66.0],
                ),
                new ShopTheLookItemStruct(
                    id: 'chair',
                    position: 1,
                    name: 'Noma Lounge Chair',
                    description: null,
                    url: '/product/noma',
                    hotspot: ['x' => 28.5, 'y' => 72.0],
                ),
            ],
            viewAll: new ShopTheLookLinkStruct('View all', '/living'),
        );

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_shop_the_look', $payload['apiAlias']);
        self::assertSame('Bring this look home.', $payload['title']);
        self::assertSame('cms_jv_shop_the_look_media', $payload['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/look.webp', $payload['image']['url']);
        self::assertCount(2, $payload['items']);
        self::assertSame('cms_jv_shop_the_look_item', $payload['items'][0]['apiAlias']);
        self::assertSame('Alba Modular Sofa', $payload['items'][0]['name']);
        self::assertSame(['x' => 69.0, 'y' => 66.0], $payload['items'][0]['hotspot']);
        self::assertSame('cms_jv_shop_the_look_item', $payload['items'][1]['apiAlias']);
        self::assertNull($payload['items'][1]['description']);
        self::assertSame('cms_jv_shop_the_look_link', $payload['viewAll']['apiAlias']);
        self::assertSame('/living', $payload['viewAll']['url']);
    }

    /** @param array{items?: mixed} $values */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, ''));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, ''));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, ''));
        $config->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, null));
        $config->add(new FieldConfig('items', FieldConfig::SOURCE_STATIC, $values['items'] ?? []));
        $config->add(new FieldConfig('viewAll', FieldConfig::SOURCE_STATIC, ['label' => '', 'url' => '']));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-shop-the-look-integration');
        $slot->setType(ShopTheLookCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
