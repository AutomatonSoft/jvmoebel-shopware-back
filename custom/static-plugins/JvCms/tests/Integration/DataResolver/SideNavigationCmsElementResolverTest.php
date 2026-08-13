<?php declare(strict_types=1);

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

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();

        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertSame('cms_jv_side_navigation', $data->getApiAlias());
        self::assertSame('assortment', $data->getDefaultTabId());
        self::assertCount(1, $data->getTabs());
        self::assertSame('/account/login', $data->getFooterItems()[0]->getHref());
    }

    private function slot(): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('logoLink', FieldConfig::SOURCE_STATIC, '/'));
        $config->add(new FieldConfig('logoMedia', FieldConfig::SOURCE_STATIC, null));
        $config->add(new FieldConfig('defaultTabId', FieldConfig::SOURCE_STATIC, 'assortment'));
        $config->add(new FieldConfig('tabs', FieldConfig::SOURCE_STATIC, [
            [
                'id' => 'assortment',
                'label' => 'Sortiment',
                'sections' => [
                    [
                        'id' => 'links',
                        'type' => 'manual-links',
                        'items' => [
                            [
                                'id' => 'brands',
                                'label' => 'Marken',
                                'url' => '/marken',
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]));
        $config->add(new FieldConfig('footer', FieldConfig::SOURCE_STATIC, [
            'items' => [
                [
                    'id' => 'login',
                    'label' => 'Anmelden',
                    'href' => '/account/login',
                    'icon' => 'login',
                    'visibility' => 'guest',
                ],
            ],
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-side-navigation-integration');
        $slot->setType(SideNavigationCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
