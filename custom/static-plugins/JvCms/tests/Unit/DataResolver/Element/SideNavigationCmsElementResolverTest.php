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
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class SideNavigationCmsElementResolverTest extends TestCase
{
    private const string ROOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string CHILD_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string MISSING_ID = 'cccccccccccccccccccccccccccccccc';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = $this->resolver();

        self::assertSame('jv-side-navigation', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testItNormalizesTabsFooterAndRelativeHrefs(): void
    {
        $slot = $this->slot([
            'logoLink' => '  /  ',
            'defaultTabId' => 'missing-tab',
            'tabs' => [
                [
                    'id' => 'assortment',
                    'label' => '  Sortiment  ',
                    'sections' => [
                        [
                            'id' => 'extra',
                            'type' => 'manual-links',
                            'style' => 'uppercase',
                            'items' => [
                                [
                                    'id' => 'brands',
                                    'label' => 'Marken',
                                    'url' => '/marken',
                                    'openInNewTab' => false,
                                    'children' => [],
                                ],
                                [
                                    'id' => 'bad',
                                    'label' => 'Bad',
                                    'url' => 'javascript:alert(1)',
                                    'children' => [],
                                ],
                            ],
                        ],
                        [
                            'id' => 'sep',
                            'type' => 'divider',
                        ],
                        [
                            'id' => 'tree',
                            'type' => 'category-tree',
                            'rootCategoryId' => 'not-a-uuid',
                        ],
                        [
                            'id' => 'unknown',
                            'type' => 'nope',
                        ],
                    ],
                ],
            ],
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

        $this->resolver($loader)->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertSame('cms_jv_side_navigation', $data->getApiAlias());
        self::assertSame('/', $data->getLogoLink());
        self::assertSame('assortment', $data->getDefaultTabId());
        self::assertCount(1, $data->getTabs());
        self::assertSame('Sortiment', $data->getTabs()[0]->getLabel());
        self::assertCount(3, $data->getTabs()[0]->getSections());

        $manual = $data->getTabs()[0]->getSections()[0];
        self::assertSame('manual-links', $manual->getType());
        self::assertSame('uppercase', $manual->getStyle());
        self::assertCount(2, $manual->getItems());
        self::assertSame('/marken', $manual->getItems()[0]->getUrl());
        self::assertNull($manual->getItems()[1]->getUrl());

        self::assertSame('divider', $data->getTabs()[0]->getSections()[1]->getType());
        self::assertSame('category-tree', $data->getTabs()[0]->getSections()[2]->getType());
        self::assertSame([], $data->getTabs()[0]->getSections()[2]->getItems());
        self::assertNull($data->getTabs()[0]->getSections()[2]->getAllLink());

        $footer = $data->getFooterItems();
        self::assertCount(2, $footer);
        self::assertSame('login', $footer[0]->getId());
        self::assertSame('guest', $footer[0]->getVisibility());
        self::assertSame('help', $footer[1]->getId());
        self::assertNull($footer[1]->getIcon());
        self::assertSame('always', $footer[1]->getVisibility());
    }

    public function testItMapsCategoryTreeFromNavigationLoader(): void
    {
        $root = new SalesChannelCategoryEntity();
        $root->setUniqueIdentifier(self::ROOT_ID);
        $root->setId(self::ROOT_ID);
        $root->setName('Root');
        $root->setTranslated(['name' => 'Root']);
        $root->setSeoUrl('/alle');

        $child = new SalesChannelCategoryEntity();
        $child->setUniqueIdentifier(self::CHILD_ID);
        $child->setId(self::CHILD_ID);
        $child->setName('Möbel');
        $child->setTranslated(['name' => 'Möbel']);
        $child->setSeoUrl('/moebel');

        $tree = new Tree($root, [
            new TreeItem($child, []),
        ]);

        $loader = $this->createMock(NavigationLoaderInterface::class);
        $loader->expects(self::once())
            ->method('load')
            ->with(self::ROOT_ID, self::anything(), self::ROOT_ID, 3)
            ->willReturn($tree);

        $slot = $this->slot([
            'tabs' => [
                [
                    'id' => 'assortment',
                    'label' => 'Sortiment',
                    'sections' => [
                        [
                            'id' => 'main-tree',
                            'type' => 'category-tree',
                            'rootCategoryId' => self::ROOT_ID,
                            'maxDepth' => 3,
                            'showIcons' => false,
                            'includeRootAsAllLink' => true,
                            'allLinkLabel' => 'Alle Artikel',
                        ],
                    ],
                ],
            ],
            'footer' => ['items' => []],
        ]);

        $this->resolver($loader)->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);

        $section = $data->getTabs()[0]->getSections()[0];
        self::assertSame('category-tree', $section->getType());
        self::assertCount(1, $section->getItems());
        self::assertSame(self::CHILD_ID, $section->getItems()[0]->getId());
        self::assertSame('category', $section->getItems()[0]->getKind());
        self::assertSame('Möbel', $section->getItems()[0]->getLabel());
        self::assertSame('/moebel', $section->getItems()[0]->getUrl());
        self::assertFalse($section->getItems()[0]->isHasChildren());

        $allLink = $section->getAllLink();
        self::assertNotNull($allLink);
        self::assertSame(self::ROOT_ID, $allLink->getId());
        self::assertSame('Alle Artikel', $allLink->getLabel());
        self::assertSame('/alle', $allLink->getUrl());
    }

    public function testValidButMissingCategoryDoesNotThrow(): void
    {
        $loader = $this->createMock(NavigationLoaderInterface::class);
        $loader->expects(self::once())
            ->method('load')
            ->with(self::MISSING_ID, self::anything(), self::MISSING_ID, 3)
            ->willThrowException(new CategoryNotFoundException(self::MISSING_ID));

        $slot = $this->slot([
            'tabs' => [[
                'id' => 't1',
                'label' => 'Tab',
                'sections' => [[
                    'id' => 'tree',
                    'type' => 'category-tree',
                    'rootCategoryId' => self::MISSING_ID,
                ]],
            ]],
            'footer' => ['items' => []],
        ]);

        $this->resolver($loader)->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);

        $section = $data->getTabs()[0]->getSections()[0];
        self::assertSame('category-tree', $section->getType());
        self::assertSame([], $section->getItems());
        self::assertNull($section->getAllLink());
    }

    public function testMalformedMediaIdsAreNotCollected(): void
    {
        $slot = $this->slot([
            'logoMedia' => 'not-a-uuid',
            'tabs' => [[
                'id' => 't1',
                'label' => 'Tab',
                'sections' => [
                    [
                        'id' => 'p1',
                        'type' => 'promo',
                        'mediaId' => 'also-bad',
                        'title' => 'Promo',
                    ],
                    [
                        'id' => 'm1',
                        'type' => 'manual-links',
                        'items' => [[
                            'id' => 'i1',
                            'label' => 'Link',
                            'url' => '/x',
                            'iconMediaId' => 'bad-icon',
                            'children' => [[
                                'id' => 'i2',
                                'label' => 'Child',
                                'url' => '/y',
                                'iconMediaId' => 'nested-bad',
                                'children' => [],
                            ]],
                        ]],
                    ],
                ],
            ]],
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
        self::assertNull($data->getTabs()[0]->getSections()[0]->getMedia());
        self::assertNull($data->getTabs()[0]->getSections()[1]->getItems()[0]->getIcon());
        self::assertNull($data->getTabs()[0]->getSections()[1]->getItems()[0]->getChildren()[0]->getIcon());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $loader = $this->createMock(NavigationLoaderInterface::class);
        $loader->expects(self::never())->method('load');

        $slot = $this->slot([
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
                            'iconMediaId' => 'bad-icon',
                            'children' => [],
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

        $this->resolver($loader)->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);

        $payload = $this->storeApiArray($data);

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
        self::assertIsArray($payload['tabs'][0]['sections']);

        $manual = $payload['tabs'][0]['sections'][0];
        self::assertIsArray($manual);
        self::assertSame('cms_jv_side_navigation_section', $manual['apiAlias']);
        self::assertIsArray($manual['items'][0]);
        self::assertSame('cms_jv_side_navigation_nav_item', $manual['items'][0]['apiAlias']);
        self::assertSame('/marken', $manual['items'][0]['url']);
        self::assertNull($manual['items'][0]['icon']);

        $tree = $payload['tabs'][0]['sections'][1];
        self::assertIsArray($tree);
        self::assertSame([], $tree['items']);
        self::assertNull($tree['allLink']);

        $promo = $payload['tabs'][0]['sections'][2];
        self::assertIsArray($promo);
        self::assertNull($promo['media']);
        self::assertSame('Sale', $promo['title']);

        self::assertIsArray($payload['footerItems'][0]);
        self::assertSame('cms_jv_side_navigation_footer_item', $payload['footerItems'][0]['apiAlias']);
        self::assertSame('/hilfe', $payload['footerItems'][0]['href']);
        self::assertNull($payload['footerItems'][0]['icon']);
        self::assertSame('always', $payload['footerItems'][0]['visibility']);
    }

    public function testEmptyTabsStaySafe(): void
    {
        $slot = $this->slot([
            'tabs' => [],
            'footer' => ['items' => []],
        ]);

        $this->resolver()->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertSame([], $data->getTabs());
        self::assertSame('', $data->getDefaultTabId());
        self::assertSame('/', $data->getLogoLink());
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsRelativeAndAbsoluteHrefs(string $href, string $expected): void
    {
        $slot = $this->slot([
            'tabs' => [
                [
                    'id' => 't1',
                    'label' => 'Tab',
                    'sections' => [
                        [
                            'id' => 'm1',
                            'type' => 'manual-links',
                            'items' => [
                                [
                                    'id' => 'i1',
                                    'label' => 'Item',
                                    'url' => $href,
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'footer' => ['items' => []],
        ]);

        $this->resolver()->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertSame($expected, $data->getTabs()[0]->getSections()[0]->getItems()[0]->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'relative' => ['/marken', '/marken'];
        yield 'relative nested' => ['/a/b', '/a/b'];
        yield 'relative with query' => ['/hilfe?x=1', '/hilfe?x=1'];
        yield 'trimmed relative' => ['  /hilfe  ', '/hilfe'];
        yield 'https host' => ['https://jvmoebel.de', 'https://jvmoebel.de'];
        yield 'https path' => ['https://jvmoebel.de/angebote', 'https://jvmoebel.de/angebote'];
        yield 'http' => ['http://example.com', 'http://example.com'];
        yield 'trimmed https' => ['  https://jvmoebel.de/angebote  ', 'https://jvmoebel.de/angebote'];
        yield 'https with port' => ['https://example.com:8443/path', 'https://example.com:8443/path'];
        yield 'https with query' => ['https://jvmoebel.de/angebote?utm=1', 'https://jvmoebel.de/angebote?utm=1'];
        yield 'http uppercase scheme' => ['HTTP://example.com', 'HTTP://example.com'];
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeHrefs(string $href): void
    {
        $slot = $this->slot([
            'tabs' => [
                [
                    'id' => 't1',
                    'label' => 'Tab',
                    'sections' => [
                        [
                            'id' => 'm1',
                            'type' => 'manual-links',
                            'items' => [
                                [
                                    'id' => 'i1',
                                    'label' => 'Item',
                                    'url' => $href,
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'footer' => ['items' => []],
        ]);

        $this->resolver()->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(SideNavigationStruct::class, $data);
        self::assertNull($data->getTabs()[0]->getSections()[0]->getItems()[0]->getUrl());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => [' '];
        yield 'tabs' => ["\t"];
        yield 'newlines' => ["\n\r"];
        yield 'mixed whitespace' => [" \t\n "];

        yield 'bare path without slash' => ['marken'];
        yield 'relative nested without slash' => ['angebote/sale'];
        yield 'query only' => ['?utm=1'];
        yield 'hash only' => ['#section'];
        yield 'protocol relative' => ['//evil.com'];

        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript uppercase' => ['JaVaScRiPt:alert(1)'];
        yield 'javascript with padding' => [' javascript:alert(1) '];
        yield 'data html' => ['data:text/html,<script>alert(1)</script>'];
        yield 'data base64' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='];
        yield 'vbscript' => ['vbscript:msgbox(1)'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'ftp' => ['ftp://example.com'];
        yield 'ftps' => ['ftps://example.com'];
        yield 'mailto' => ['mailto:test@example.com'];
        yield 'ssh' => ['ssh://example.com'];
        yield 'ws' => ['ws://example.com'];
        yield 'wss' => ['wss://example.com'];

        yield 'https without host' => ['https://'];
        yield 'http without host' => ['http://'];
        yield 'https empty host' => ['https:///foo'];
        yield 'http empty host' => ['http:///foo'];
        yield 'https empty host trailing slash' => ['https:///'];
        yield 'scheme only https' => ['https:'];
        yield 'no host with path-looking' => ['https:/angebote'];

        yield 'newline injection' => ["https://jvmoebel.de\njavascript:alert(1)"];
        yield 'crlf injection' => ["https://jvmoebel.de\r\njavascript:alert(1)"];
        yield 'null byte style junk' => ["https://jvmoebel.de\0.evil.com"];
    }

    /**
     * Store API adds apiAlias via StructEncoder, not jsonSerialize().
     *
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
     *     defaultTabId?: string,
     *     tabs?: list<mixed>,
     *     footer?: array<string, mixed>
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('logoLink', FieldConfig::SOURCE_STATIC, $values['logoLink'] ?? ''));
        $collection->add(new FieldConfig('logoMedia', FieldConfig::SOURCE_STATIC, $values['logoMedia'] ?? null));
        $collection->add(new FieldConfig('defaultTabId', FieldConfig::SOURCE_STATIC, $values['defaultTabId'] ?? ''));
        $collection->add(new FieldConfig('tabs', FieldConfig::SOURCE_STATIC, $values['tabs'] ?? []));
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
