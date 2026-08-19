<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\SideNavigationCmsElementResolver;
use Jv\Cms\DataResolver\Element\SideNavigationStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for the Store API contract of `jv-side-navigation` (no tabs, 4-level tree, search placeholder).
 */
final class SideNavigationCmsElementResolverTest extends TestCase
{
    private const string ROOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string CHILD_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string MISSING_ID = 'cccccccccccccccccccccccccccccccc';
    private const string L2_ID = 'dddddddddddddddddddddddddddddddd';
    private const string L3_ID = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
    private const string L4_ID = 'ffffffffffffffffffffffffffffffff';
    private const string L5_ID = '11111111111111111111111111111111';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = $this->resolver();

        self::assertSame('jv-side-navigation', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testItNormalizesFooterAndSearchPlaceholder(): void
    {
        $slot = $this->slot([
            'logoLink' => '  /  ',
            'searchPlaceholder' => '  Suche  ',
            'footer' => [
                'items' => [
                    [
                        'id' => 'login',
                        'label' => 'Anmelden',
                        'href' => '/account/login',
                        'icon' => 'login',
                        'visibility' => 'guest',
                    ],
                    [
                        'id' => 'broken',
                        'label' => '',
                        'href' => '/x',
                        'icon' => 'login',
                        'visibility' => 'always',
                    ],
                    [
                        'id' => 'orders',
                        'label' => 'Orders',
                        'href' => 'javascript:alert(1)',
                        'icon' => 'orders',
                        'visibility' => 'always',
                    ],
                    [
                        'id' => 'help',
                        'label' => 'Hilfe',
                        'href' => '/hilfe',
                        'icon' => 'unknown-icon',
                        'visibility' => 'weird',
                    ],
                ],
            ],
        ]);

        $loader = $this->createMock(NavigationLoaderInterface::class);
        $loader->expects(self::never())->method('load');

        $this->resolver($loader)->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertSame('cms_jv_side_navigation', $data->getApiAlias());
        self::assertSame('/', $data->getLogoLink());
        self::assertSame('Suche', $data->getSearchPlaceholder());
        self::assertSame([], $data->getItems());

        $footer = $data->getFooterItems();
        self::assertCount(2, $footer);
        self::assertSame('login', $footer[0]->getId());
        self::assertSame('guest', $footer[0]->getVisibility());
        self::assertSame('help', $footer[1]->getId());
        self::assertNull($footer[1]->getIcon());
        self::assertSame('always', $footer[1]->getVisibility());
    }

    public function testEmptyPlaceholderFallsBackToDefault(): void
    {
        $slot = $this->slot([
            'searchPlaceholder' => '   ',
            'footer' => ['items' => []],
        ]);

        $this->resolver()->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertSame('Kategorie suchen', $data->getSearchPlaceholder());
        self::assertSame([], $data->getItems());
    }

    public function testItMapsCategoryTreeFromNavigationLoader(): void
    {
        $root = $this->category(self::ROOT_ID, 'Root', '/alle');
        $child = $this->category(self::CHILD_ID, 'Möbel', '/moebel');

        $tree = new Tree($root, [
            new TreeItem($child, []),
        ]);

        $loader = $this->createMock(NavigationLoaderInterface::class);
        $loader->expects(self::once())
            ->method('load')
            ->with(self::ROOT_ID, self::anything(), self::ROOT_ID, 4)
            ->willReturn($tree);

        $slot = $this->slot([
            'rootCategoryId' => self::ROOT_ID,
            'showIcons' => false,
            'footer' => ['items' => []],
        ]);

        $this->resolver($loader)->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertCount(1, $data->getItems());
        self::assertSame(self::CHILD_ID, $data->getItems()[0]->getId());
        self::assertSame('category', $data->getItems()[0]->getKind());
        self::assertSame('Möbel', $data->getItems()[0]->getLabel());
        self::assertSame('/moebel', $data->getItems()[0]->getUrl());
        self::assertFalse($data->getItems()[0]->isHasChildren());
    }

    public function testCategoryTreeKeepsFourLevelsAndDropsTheFifth(): void
    {
        $l5 = $this->category(self::L5_ID, 'L5', '/l5');
        $l4 = $this->category(self::L4_ID, 'L4', '/l4');
        $l3 = $this->category(self::L3_ID, 'L3', '/l3');
        $l2 = $this->category(self::L2_ID, 'L2', '/l2');
        $l1 = $this->category(self::CHILD_ID, 'L1', '/l1');
        $root = $this->category(self::ROOT_ID, 'Root', '/alle');

        $tree = new Tree($root, [
            new TreeItem($l1, [
                new TreeItem($l2, [
                    new TreeItem($l3, [
                        new TreeItem($l4, [
                            new TreeItem($l5, []),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $loader = $this->createMock(NavigationLoaderInterface::class);
        $loader->expects(self::once())
            ->method('load')
            ->with(self::ROOT_ID, self::anything(), self::ROOT_ID, 4)
            ->willReturn($tree);

        $slot = $this->slot([
            'rootCategoryId' => self::ROOT_ID,
            'showIcons' => false,
            'footer' => ['items' => []],
        ]);

        $this->resolver($loader)->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);

        $l1Item = $data->getItems()[0];
        self::assertSame(self::CHILD_ID, $l1Item->getId());
        self::assertTrue($l1Item->isHasChildren());

        $l2Item = $l1Item->getChildren()[0];
        self::assertSame(self::L2_ID, $l2Item->getId());
        self::assertTrue($l2Item->isHasChildren());

        $l3Item = $l2Item->getChildren()[0];
        self::assertSame(self::L3_ID, $l3Item->getId());
        self::assertTrue($l3Item->isHasChildren());

        $l4Item = $l3Item->getChildren()[0];
        self::assertSame(self::L4_ID, $l4Item->getId());
        self::assertFalse($l4Item->isHasChildren());
        self::assertSame([], $l4Item->getChildren());
    }

    public function testValidButMissingCategoryDoesNotThrow(): void
    {
        $loader = $this->createMock(NavigationLoaderInterface::class);
        $loader->expects(self::once())
            ->method('load')
            ->with(self::MISSING_ID, self::anything(), self::MISSING_ID, 4)
            ->willThrowException(new CategoryNotFoundException(self::MISSING_ID));

        $slot = $this->slot([
            'rootCategoryId' => self::MISSING_ID,
            'footer' => ['items' => []],
        ]);

        $this->resolver($loader)->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertSame([], $data->getItems());
    }

    public function testMalformedRootAndLogoAreNotLoaded(): void
    {
        $slot = $this->slot([
            'logoMedia' => 'not-a-uuid',
            'rootCategoryId' => 'not-a-uuid',
            'footer' => ['items' => []],
        ]);

        $loader = $this->createMock(NavigationLoaderInterface::class);
        $loader->expects(self::never())->method('load');

        $resolver = $this->resolver($loader);
        self::assertNull($resolver->collect($slot, $this->resolverContext()));

        $resolver->enrich($slot, $this->resolverContext(), new ElementDataCollection());
        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertNull($data->getLogo());
        self::assertSame([], $data->getItems());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $loader = $this->createMock(NavigationLoaderInterface::class);
        $loader->expects(self::never())->method('load');

        $slot = $this->slot([
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

        $this->resolver($loader)->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_side_navigation', $payload['apiAlias']);
        self::assertArrayHasKey('logo', $payload);
        self::assertArrayHasKey('logoLink', $payload);
        self::assertArrayHasKey('searchPlaceholder', $payload);
        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('footerItems', $payload);
        self::assertArrayNotHasKey('tabs', $payload);
        self::assertArrayNotHasKey('defaultTabId', $payload);
        self::assertNull($payload['logo']);
        self::assertSame('/', $payload['logoLink']);
        self::assertSame('Kategorie suchen', $payload['searchPlaceholder']);
        self::assertSame([], $payload['items']);
        self::assertIsArray($payload['footerItems'][0]);
        self::assertSame('cms_jv_side_navigation_footer_item', $payload['footerItems'][0]['apiAlias']);
        self::assertNull($payload['footerItems'][0]['icon']);
        self::assertSame('always', $payload['footerItems'][0]['visibility']);
    }

    public function testSerializedCategoryTreeExposesContract(): void
    {
        $icon = new MediaEntity();
        $icon->setUniqueIdentifier('22222222222222222222222222222222');
        $icon->setUrl('https://cdn.example.com/moebel.png');
        $icon->setFileName('moebel.png');

        $child = $this->category(self::CHILD_ID, 'Möbel', '/moebel');
        $child->setMedia($icon);
        $nested = $this->category(self::L2_ID, 'Sofas', '/sofas');
        $root = $this->category(self::ROOT_ID, 'Root', '/alle');

        $tree = new Tree($root, [
            new TreeItem($child, [
                new TreeItem($nested, []),
            ]),
        ]);

        $loader = $this->createMock(NavigationLoaderInterface::class);
        $loader->expects(self::once())->method('load')->willReturn($tree);

        $slot = $this->slot([
            'rootCategoryId' => self::ROOT_ID,
            'showIcons' => true,
            'footer' => ['items' => []],
        ]);

        $this->resolver($loader)->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);

        $payload = $this->storeApiArray($data);
        $item = $payload['items'][0];
        self::assertIsArray($item);
        self::assertSame('cms_jv_side_navigation_nav_item', $item['apiAlias']);
        self::assertSame('category', $item['kind']);
        self::assertSame(self::CHILD_ID, $item['id']);
        self::assertSame('/moebel', $item['url']);
        self::assertTrue($item['hasChildren']);
        self::assertIsArray($item['icon']);
        self::assertSame('cms_jv_side_navigation_media_ref', $item['icon']['apiAlias']);
        self::assertSame('https://cdn.example.com/moebel.png', $item['icon']['url']);
        self::assertIsArray($item['children'][0]);
        self::assertSame(self::L2_ID, $item['children'][0]['id']);
        self::assertSame('/sofas', $item['children'][0]['url']);
        self::assertFalse($item['children'][0]['hasChildren']);
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsRelativeAndAbsoluteHrefs(string $href, string $expected): void
    {
        $slot = $this->slot([
            'footer' => [
                'items' => [[
                    'id' => 'i1',
                    'label' => 'Item',
                    'href' => $href,
                    'icon' => 'help',
                    'visibility' => 'always',
                ]],
            ],
        ]);

        $this->resolver()->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertSame($expected, $data->getFooterItems()[0]->getHref());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'relative' => ['/marken', '/marken'];
        yield 'https path' => ['https://jvmoebel.de/angebote', 'https://jvmoebel.de/angebote'];
        yield 'trimmed relative' => ['  /hilfe  ', '/hilfe'];
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeHrefs(string $href): void
    {
        $slot = $this->slot([
            'footer' => [
                'items' => [[
                    'id' => 'i1',
                    'label' => 'Item',
                    'href' => $href,
                    'icon' => 'help',
                    'visibility' => 'always',
                ]],
            ],
        ]);

        $this->resolver()->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertSame([], $data->getFooterItems());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'empty' => [''];
    }

    /**
     * @return array<string, mixed>
     */
    private function storeApiArray(Struct $struct): array
    {
        $payload = $struct->jsonSerialize();
        foreach ($payload as $key => $value) {
            if ($value instanceof Struct) {
                $payload[$key] = $this->storeApiArray($value);
            } elseif (\is_array($value)) {
                $payload[$key] = $this->storeApiList($value);
            }
        }

        $payload['apiAlias'] = $struct->getApiAlias();
        if (isset($payload['extensions']) && [] === $payload['extensions']) {
            unset($payload['extensions']);
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function storeApiList(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof Struct) {
                $values[$key] = $this->storeApiArray($value);
            } elseif (\is_array($value)) {
                $values[$key] = $this->storeApiList($value);
            }
        }

        return $values;
    }

    private function category(string $id, string $name, string $seoUrl): SalesChannelCategoryEntity
    {
        $category = new SalesChannelCategoryEntity();
        $category->setUniqueIdentifier($id);
        $category->setId($id);
        $category->setName($name);
        $category->setTranslated(['name' => $name]);
        $category->setSeoUrl($seoUrl);

        return $category;
    }

    private function resolver(?NavigationLoaderInterface $loader = null): SideNavigationCmsElementResolver
    {
        if (null === $loader) {
            $loader = $this->createMock(NavigationLoaderInterface::class);
            $loader->method('load')->willReturn(new Tree(null, []));
        }

        return new SideNavigationCmsElementResolver($loader);
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
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('logoLink', FieldConfig::SOURCE_STATIC, $values['logoLink'] ?? ''));
        $collection->add(new FieldConfig('logoMedia', FieldConfig::SOURCE_STATIC, $values['logoMedia'] ?? null));
        $collection->add(new FieldConfig('searchPlaceholder', FieldConfig::SOURCE_STATIC, $values['searchPlaceholder'] ?? ''));
        $collection->add(new FieldConfig('rootCategoryId', FieldConfig::SOURCE_STATIC, $values['rootCategoryId'] ?? null));
        $collection->add(new FieldConfig('showIcons', FieldConfig::SOURCE_STATIC, $values['showIcons'] ?? true));
        $collection->add(new FieldConfig('footer', FieldConfig::SOURCE_STATIC, $values['footer'] ?? ['items' => []]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-side-navigation');
        $slot->setType(SideNavigationCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        return $slot;
    }

    private function resolverContext(): ResolverContext
    {
        return new ResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
        );
    }
}
