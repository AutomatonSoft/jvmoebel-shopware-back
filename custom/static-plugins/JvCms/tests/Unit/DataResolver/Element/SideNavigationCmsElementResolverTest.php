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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class SideNavigationCmsElementResolverTest extends TestCase
{
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
                            'rootCategoryId' => 'missing-category',
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
        $loader->expects(self::once())
            ->method('load')
            ->willThrowException(new CategoryNotFoundException('missing-category'));

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
        $root->setUniqueIdentifier('root-id');
        $root->setId('root-id');
        $root->setName('Root');
        $root->setTranslated(['name' => 'Root']);
        $root->setSeoUrl('/alle');

        $child = new SalesChannelCategoryEntity();
        $child->setUniqueIdentifier('child-id');
        $child->setId('child-id');
        $child->setName('Möbel');
        $child->setTranslated(['name' => 'Möbel']);
        $child->setSeoUrl('/moebel');

        $tree = new Tree($root, [
            new TreeItem($child, []),
        ]);

        $loader = $this->createMock(NavigationLoaderInterface::class);
        $loader->expects(self::once())
            ->method('load')
            ->with('root-id', self::anything(), 'root-id', 3)
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
                            'rootCategoryId' => 'root-id',
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
        self::assertSame('child-id', $section->getItems()[0]->getId());
        self::assertSame('category', $section->getItems()[0]->getKind());
        self::assertSame('Möbel', $section->getItems()[0]->getLabel());
        self::assertSame('/moebel', $section->getItems()[0]->getUrl());
        self::assertFalse($section->getItems()[0]->isHasChildren());

        $allLink = $section->getAllLink();
        self::assertNotNull($allLink);
        self::assertSame('root-id', $allLink->getId());
        self::assertSame('Alle Artikel', $allLink->getLabel());
        self::assertSame('/alle', $allLink->getUrl());
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
     * Side-nav allowlist: relative `/path` and http(s) with host. Unlike jv-button, relative is accepted.
     *
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
        // empty / whitespace
        yield 'empty' => [''];
        yield 'spaces' => [' '];
        yield 'tabs' => ["\t"];
        yield 'newlines' => ["\n\r"];
        yield 'mixed whitespace' => [" \t\n "];

        // not a rooted relative path (side-nav allows `/…` only, not bare paths)
        yield 'bare path without slash' => ['marken'];
        yield 'relative nested without slash' => ['angebote/sale'];
        yield 'query only' => ['?utm=1'];
        yield 'hash only' => ['#section'];
        yield 'protocol relative' => ['//evil.com'];

        // dangerous / non-http schemes
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

        // incomplete http(s)
        yield 'https without host' => ['https://'];
        yield 'http without host' => ['http://'];
        yield 'https empty host' => ['https:///foo'];
        yield 'http empty host' => ['http:///foo'];
        yield 'https empty host trailing slash' => ['https:///'];
        yield 'scheme only https' => ['https:'];
        yield 'no host with path-looking' => ['https:/angebote'];

        // malformed / injection-ish
        yield 'newline injection' => ["https://jvmoebel.de\njavascript:alert(1)"];
        yield 'crlf injection' => ["https://jvmoebel.de\r\njavascript:alert(1)"];
        yield 'null byte style junk' => ["https://jvmoebel.de\0.evil.com"];
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
