<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\SideNavigationCmsElementResolver;
use Jv\Cms\DataResolver\Element\SideNavigationStruct;
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

final class SideNavigationCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(SideNavigationCmsElementResolver::class);
        self::assertInstanceOf(SideNavigationCmsElementResolver::class, $resolver);
        self::assertSame('jv-side-navigation', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->slot();

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-side-navigation', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertSame('cms_jv_side_navigation', $data->getApiAlias());
        self::assertSame('assortment', $data->getDefaultTabId());
        self::assertCount(1, $data->getTabs());
        self::assertSame('/account/login', $data->getFooterItems()[0]->getHref());
    }

    public function testStoreApiEncoderExposesSerializedContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->contractSlot();

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-side-navigation', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_side_navigation', $payload['apiAlias']);
        self::assertArrayHasKey('logo', $payload);
        self::assertArrayHasKey('logoLink', $payload);
        self::assertArrayHasKey('defaultTabId', $payload);
        self::assertArrayHasKey('tabs', $payload);
        self::assertArrayHasKey('footerItems', $payload);
        self::assertNull($payload['logo']);
        self::assertSame('/', $payload['logoLink']);
        self::assertSame('assortment', $payload['defaultTabId']);

        self::assertIsArray($payload['tabs']);
        self::assertArrayHasKey(0, $payload['tabs']);
        self::assertIsArray($payload['tabs'][0]);
        self::assertSame('cms_jv_side_navigation_tab', $payload['tabs'][0]['apiAlias']);
        self::assertSame('assortment', $payload['tabs'][0]['id']);
        self::assertSame('Sortiment', $payload['tabs'][0]['label']);
        self::assertIsArray($payload['tabs'][0]['sections']);
        self::assertCount(3, $payload['tabs'][0]['sections']);

        $manual = $payload['tabs'][0]['sections'][0];
        self::assertIsArray($manual);
        self::assertSame('cms_jv_side_navigation_section', $manual['apiAlias']);
        self::assertSame('manual-links', $manual['type']);
        self::assertSame('default', $manual['style']);
        self::assertIsArray($manual['items'][0]);
        self::assertSame('cms_jv_side_navigation_nav_item', $manual['items'][0]['apiAlias']);
        self::assertSame('link', $manual['items'][0]['kind']);
        self::assertSame('/marken', $manual['items'][0]['url']);
        self::assertFalse($manual['items'][0]['openInNewTab']);
        self::assertTrue($manual['items'][0]['hasChildren']);
        self::assertNull($manual['items'][0]['icon']);
        self::assertIsArray($manual['items'][0]['children'][0]);
        self::assertSame('cms_jv_side_navigation_nav_item', $manual['items'][0]['children'][0]['apiAlias']);
        self::assertSame('nested', $manual['items'][0]['children'][0]['id']);
        self::assertSame('/nested', $manual['items'][0]['children'][0]['url']);
        self::assertFalse($manual['items'][0]['children'][0]['hasChildren']);

        $tree = $payload['tabs'][0]['sections'][1];
        self::assertIsArray($tree);
        self::assertSame('category-tree', $tree['type']);
        self::assertSame([], $tree['items']);
        self::assertNull($tree['allLink']);

        $promo = $payload['tabs'][0]['sections'][2];
        self::assertIsArray($promo);
        self::assertSame('promo', $promo['type']);
        self::assertNull($promo['media']);
        self::assertSame('Sale', $promo['title']);
        self::assertSame('/sale', $promo['url']);

        self::assertIsArray($payload['footerItems'][0]);
        self::assertSame('cms_jv_side_navigation_footer_item', $payload['footerItems'][0]['apiAlias']);
        self::assertSame('help', $payload['footerItems'][0]['id']);
        self::assertSame('/hilfe', $payload['footerItems'][0]['href']);
        self::assertNull($payload['footerItems'][0]['icon']);
        self::assertSame('always', $payload['footerItems'][0]['visibility']);
    }

    private function slot(): CmsSlotEntity
    {
        return $this->createSlot([
            'logoLink' => '/',
            'logoMedia' => null,
            'defaultTabId' => 'assortment',
            'tabs' => [[
                'id' => 'assortment',
                'label' => 'Sortiment',
                'sections' => [[
                    'id' => 'links',
                    'type' => 'manual-links',
                    'items' => [[
                        'id' => 'brands',
                        'label' => 'Marken',
                        'url' => '/marken',
                        'children' => [],
                    ]],
                ]],
            ]],
            'footer' => [
                'items' => [[
                    'id' => 'login',
                    'label' => 'Anmelden',
                    'href' => '/account/login',
                    'icon' => 'login',
                    'visibility' => 'guest',
                ]],
            ],
        ]);
    }

    private function contractSlot(): CmsSlotEntity
    {
        return $this->createSlot([
            'logoLink' => '/',
            'logoMedia' => 'not-a-uuid',
            'defaultTabId' => 'assortment',
            'tabs' => [[
                'id' => 'assortment',
                'label' => 'Sortiment',
                'sections' => [
                    [
                        'id' => 'links',
                        'type' => 'manual-links',
                        'style' => 'default',
                        'items' => [[
                            'id' => 'brands',
                            'label' => 'Marken',
                            'url' => '/marken',
                            'openInNewTab' => false,
                            'iconMediaId' => 'bad-icon',
                            'children' => [[
                                'id' => 'nested',
                                'label' => 'Nested',
                                'url' => '/nested',
                                'children' => [],
                            ]],
                        ]],
                    ],
                    [
                        'id' => 'tree',
                        'type' => 'category-tree',
                        'rootCategoryId' => 'not-a-uuid',
                        'includeRootAsAllLink' => true,
                    ],
                    [
                        'id' => 'promo',
                        'type' => 'promo',
                        'mediaId' => 'bad-media',
                        'title' => 'Sale',
                        'url' => '/sale',
                    ],
                ],
            ]],
            'footer' => [
                'items' => [[
                    'id' => 'help',
                    'label' => 'Hilfe',
                    'href' => '/hilfe',
                    'icon' => 'unknown-icon',
                    'visibility' => 'weird',
                ]],
            ],
        ]);
    }

    /**
     * @param array{
     *     logoLink?: string,
     *     logoMedia?: string|null,
     *     defaultTabId?: string,
     *     tabs?: list<mixed>,
     *     footer?: array<string, mixed>
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('logoLink', FieldConfig::SOURCE_STATIC, $values['logoLink'] ?? ''));
        $config->add(new FieldConfig('logoMedia', FieldConfig::SOURCE_STATIC, $values['logoMedia'] ?? null));
        $config->add(new FieldConfig('defaultTabId', FieldConfig::SOURCE_STATIC, $values['defaultTabId'] ?? ''));
        $config->add(new FieldConfig('tabs', FieldConfig::SOURCE_STATIC, $values['tabs'] ?? []));
        $config->add(new FieldConfig('footer', FieldConfig::SOURCE_STATIC, $values['footer'] ?? ['items' => []]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-side-navigation-integration');
        $slot->setType(SideNavigationCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
