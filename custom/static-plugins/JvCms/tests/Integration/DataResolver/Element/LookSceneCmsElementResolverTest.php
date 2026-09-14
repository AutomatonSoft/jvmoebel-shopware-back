<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\LookSceneCmsElementResolver;
use Jv\Cms\DataResolver\Element\LookSceneLinkStruct;
use Jv\Cms\DataResolver\Element\LookSceneMediaStruct;
use Jv\Cms\DataResolver\Element\LookSceneProductStruct;
use Jv\Cms\DataResolver\Element\LookSceneStruct;
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

final class LookSceneCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(LookSceneCmsElementResolver::class);
        self::assertInstanceOf(LookSceneCmsElementResolver::class, $resolver);
        self::assertSame('jv-look-scene', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot(['products' => 'broken']);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-look-scene', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(LookSceneStruct::class, $data);
        self::assertSame('cms_jv_look_scene', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getImage());
        self::assertSame([], $data->getProducts());
        self::assertNull($data->getViewAll());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'description' => '  ',
            'imageMedia' => 'not-a-uuid',
            'products' => [
                [
                    'productId' => 'invalid',
                    'name' => 'Manual fallback must not be used',
                    'url' => '/manual',
                ],
                [
                    'name' => 'Broken manual',
                    'url' => 'javascript:alert(1)',
                ],
            ],
            'viewAll' => [
                'label' => 'All',
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

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(LookSceneStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_look_scene', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['description']);
        self::assertNull($payload['image']);
        self::assertSame([], $payload['products']);
        self::assertNull($payload['viewAll']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new LookSceneStruct(
            title: 'Living room scene',
            description: 'Curated pieces',
            image: new LookSceneMediaStruct('https://cdn.example.com/scene.webp', 'Living room'),
            products: [
                new LookSceneProductStruct(
                    id: 'sofa',
                    position: 0,
                    name: 'Alba Modular Sofa',
                    url: '/produkt/sofa',
                ),
                new LookSceneProductStruct(
                    id: 'chair',
                    position: 1,
                    name: 'Noma Lounge Chair',
                    url: '/product/noma',
                ),
            ],
            viewAll: new LookSceneLinkStruct('View all', '/living'),
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_look_scene', $payload['apiAlias']);
        self::assertSame('Living room scene', $payload['title']);
        self::assertSame('cms_jv_look_scene_media', $payload['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/scene.webp', $payload['image']['url']);
        self::assertCount(2, $payload['products']);
        self::assertSame('cms_jv_look_scene_product', $payload['products'][0]['apiAlias']);
        self::assertSame('Alba Modular Sofa', $payload['products'][0]['name']);
        self::assertSame('cms_jv_look_scene_link', $payload['viewAll']['apiAlias']);
        self::assertSame('/living', $payload['viewAll']['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $config->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, $values['products'] ?? []));
        $config->add(new FieldConfig('viewAll', FieldConfig::SOURCE_STATIC, $values['viewAll'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-look-scene');
        $slot->setType(LookSceneCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
