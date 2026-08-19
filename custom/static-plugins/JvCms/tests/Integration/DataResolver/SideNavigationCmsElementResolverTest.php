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

/**
 * CmsSlotsDataResolver + StructEncoder: serialized keys match SPEC-003 (`searchPlaceholder`, `items`, no `tabs`).
 */
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

        $slot = $this->createSlot([
            'logoLink' => '/',
            'searchPlaceholder' => 'Suche',
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
        self::assertSame('Suche', $data->getSearchPlaceholder());
        self::assertSame([], $data->getItems());
        self::assertSame('/account/login', $data->getFooterItems()[0]->getHref());
    }

    public function testStoreApiEncoderExposesSerializedContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'logoLink' => '/',
            'logoMedia' => 'not-a-uuid',
            'searchPlaceholder' => 'Kategorie suchen',
            'rootCategoryId' => 'not-a-uuid',
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
        self::assertArrayHasKey('searchPlaceholder', $payload);
        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('footerItems', $payload);
        self::assertArrayNotHasKey('tabs', $payload);
        self::assertNull($payload['logo']);
        self::assertSame('/', $payload['logoLink']);
        self::assertSame('Kategorie suchen', $payload['searchPlaceholder']);
        self::assertSame([], $payload['items']);
        self::assertIsArray($payload['footerItems'][0]);
        self::assertSame('cms_jv_side_navigation_footer_item', $payload['footerItems'][0]['apiAlias']);
        self::assertNull($payload['footerItems'][0]['icon']);
        self::assertSame('always', $payload['footerItems'][0]['visibility']);
    }

    /**
     * @param array{
     *     logoLink?: string,
     *     logoMedia?: string|null,
     *     searchPlaceholder?: string,
     *     rootCategoryId?: string|null,
     *     showIcons?: bool,
     *     footer?: array<string, mixed>
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('logoLink', FieldConfig::SOURCE_STATIC, $values['logoLink'] ?? ''));
        $config->add(new FieldConfig('logoMedia', FieldConfig::SOURCE_STATIC, $values['logoMedia'] ?? null));
        $config->add(new FieldConfig('searchPlaceholder', FieldConfig::SOURCE_STATIC, $values['searchPlaceholder'] ?? ''));
        $config->add(new FieldConfig('rootCategoryId', FieldConfig::SOURCE_STATIC, $values['rootCategoryId'] ?? null));
        $config->add(new FieldConfig('showIcons', FieldConfig::SOURCE_STATIC, $values['showIcons'] ?? true));
        $config->add(new FieldConfig('footer', FieldConfig::SOURCE_STATIC, $values['footer'] ?? ['items' => []]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-side-navigation-integration');
        $slot->setType(SideNavigationCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
