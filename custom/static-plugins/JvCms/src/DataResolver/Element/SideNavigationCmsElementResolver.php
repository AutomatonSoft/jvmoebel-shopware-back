<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Jv\Cms\DataResolver\Element\SideNavigation\FooterItem;
use Jv\Cms\DataResolver\Element\SideNavigation\MediaRef;
use Jv\Cms\DataResolver\Element\SideNavigation\NavItem;
use Jv\Cms\DataResolver\Element\SideNavigation\Section;
use Jv\Cms\DataResolver\Element\SideNavigation\Tab;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class SideNavigationCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-side-navigation';

    private const array FOOTER_ICONS = [
        'login',
        'user',
        'orders',
        'returns',
        'help',
        'grid',
        'recent',
    ];

    private const array FOOTER_VISIBILITIES = [
        'always',
        'guest',
        'customer',
    ];

    public function __construct(
        private readonly NavigationLoaderInterface $navigationLoader,
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $mediaIds = [];

        $logoMedia = $config->get('logoMedia');
        if (null !== $logoMedia) {
            $id = $this->normalizeUuid($logoMedia->getValue());
            if (null !== $id) {
                $mediaIds[] = $id;
            }
        }

        $tabs = $this->configArray($config->get('tabs')?->getValue());
        foreach ($tabs as $tab) {
            if (!\is_array($tab)) {
                continue;
            }
            $sections = $this->configArray($tab['sections'] ?? null);
            foreach ($sections as $section) {
                if (!\is_array($section)) {
                    continue;
                }
                $this->collectMediaIdsFromSection($section, $mediaIds);
            }
        }

        $mediaIds = array_values(array_unique($mediaIds));
        if ([] === $mediaIds) {
            return null;
        }

        $criteria = new Criteria($mediaIds);
        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'jv_side_navigation_media_'.$slot->getUniqueIdentifier(),
            MediaDefinition::class,
            $criteria,
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $media = $this->mediaMap($result->get('jv_side_navigation_media_'.$slot->getUniqueIdentifier()));
        $salesChannelContext = $resolverContext->getSalesChannelContext();

        $logoLink = $this->safeHref($config->get('logoLink')?->getStringValue()) ?? '/';
        $logoMediaId = $this->normalizeUuid($config->get('logoMedia')?->getValue());
        $logo = $this->mediaRef(null !== $logoMediaId ? ($media[$logoMediaId] ?? null) : null);

        $tabs = $this->normalizeTabs(
            $this->configArray($config->get('tabs')?->getValue()),
            $media,
            $salesChannelContext,
        );

        $defaultTabId = trim((string) ($config->get('defaultTabId')?->getValue() ?? ''));
        if ('' === $defaultTabId || !$this->tabExists($tabs, $defaultTabId)) {
            $defaultTabId = [] === $tabs ? '' : $tabs[0]->getId();
        }

        $footerValue = $config->get('footer')?->getValue();
        $footerItemsRaw = [];
        if (\is_array($footerValue) && isset($footerValue['items']) && \is_array($footerValue['items'])) {
            $footerItemsRaw = $footerValue['items'];
        }

        $slot->setData(new SideNavigationStruct(
            logo: $logo,
            logoLink: $logoLink,
            defaultTabId: $defaultTabId,
            tabs: $tabs,
            footerItems: $this->normalizeFooterItems($footerItemsRaw),
        ));
    }

    /**
     * @param list<string>         $mediaIds
     * @param array<string, mixed> $section
     */
    private function collectMediaIdsFromSection(array $section, array &$mediaIds): void
    {
        $type = (string) ($section['type'] ?? '');

        if ('promo' === $type) {
            $id = $this->normalizeUuid($section['mediaId'] ?? null);
            if (null !== $id) {
                $mediaIds[] = $id;
            }

            return;
        }

        if ('manual-links' === $type) {
            $this->collectMediaIdsFromManualItems($this->configArray($section['items'] ?? null), $mediaIds);
        }
    }

    /**
     * @param list<mixed>  $items
     * @param list<string> $mediaIds
     */
    private function collectMediaIdsFromManualItems(array $items, array &$mediaIds): void
    {
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $id = $this->normalizeUuid($item['iconMediaId'] ?? null);
            if (null !== $id) {
                $mediaIds[] = $id;
            }
            $this->collectMediaIdsFromManualItems($this->configArray($item['children'] ?? null), $mediaIds);
        }
    }

    /**
     * @param list<mixed>                $tabs
     * @param array<string, MediaEntity> $media
     *
     * @return list<Tab>
     */
    private function normalizeTabs(array $tabs, array $media, SalesChannelContext $salesChannelContext): array
    {
        $normalized = [];

        foreach ($tabs as $tab) {
            if (!\is_array($tab)) {
                continue;
            }

            $id = trim((string) ($tab['id'] ?? ''));
            $label = trim((string) ($tab['label'] ?? ''));
            if ('' === $id || '' === $label) {
                continue;
            }

            $sections = [];
            foreach ($this->configArray($tab['sections'] ?? null) as $section) {
                if (!\is_array($section)) {
                    continue;
                }
                $normalizedSection = $this->normalizeSection($section, $media, $salesChannelContext);
                if (null !== $normalizedSection) {
                    $sections[] = $normalizedSection;
                }
            }

            $normalized[] = new Tab($id, $label, $sections);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed>       $section
     * @param array<string, MediaEntity> $media
     */
    private function normalizeSection(array $section, array $media, SalesChannelContext $salesChannelContext): ?Section
    {
        $id = trim((string) ($section['id'] ?? ''));
        $type = trim((string) ($section['type'] ?? ''));
        if ('' === $id || '' === $type) {
            return null;
        }

        return match ($type) {
            'divider' => new Section(id: $id, type: 'divider'),
            'manual-links' => new Section(
                id: $id,
                type: 'manual-links',
                items: $this->normalizeManualItems($this->configArray($section['items'] ?? null), $media),
                style: $this->normalizeManualStyle($section['style'] ?? null),
            ),
            'promo' => new Section(
                id: $id,
                type: 'promo',
                media: $this->mediaRef(
                    $media[$this->normalizeUuid($section['mediaId'] ?? null) ?? ''] ?? null,
                    trim((string) ($section['alt'] ?? '')),
                ),
                title: '' !== ($promoTitle = trim((string) ($section['title'] ?? ''))) ? $promoTitle : null,
                url: $this->safeHref(isset($section['url']) ? (string) $section['url'] : null),
            ),
            'category-tree' => $this->normalizeCategoryTreeSection($id, $section, $salesChannelContext),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $section
     */
    private function normalizeCategoryTreeSection(string $id, array $section, SalesChannelContext $salesChannelContext): Section
    {
        $rootId = $this->normalizeUuid($section['rootCategoryId'] ?? null);
        $maxDepth = $this->clampMaxDepth($section['maxDepth'] ?? 3);
        $showIcons = (bool) ($section['showIcons'] ?? true);
        $includeAll = (bool) ($section['includeRootAsAllLink'] ?? false);
        $allLabel = trim((string) ($section['allLinkLabel'] ?? ''));

        if (null === $rootId) {
            return new Section(id: $id, type: 'category-tree', items: [], allLink: null);
        }

        try {
            $tree = $this->navigationLoader->load($rootId, $salesChannelContext, $rootId, $maxDepth);
        } catch (CategoryNotFoundException) {
            return new Section(id: $id, type: 'category-tree', items: [], allLink: null);
        }

        $items = $this->mapTreeItems($tree->getTree(), $showIcons);

        $allLink = null;
        $active = $tree->getActive();
        if ($includeAll && null !== $active) {
            $label = '' !== $allLabel ? $allLabel : $this->categoryLabel($active);
            $allLink = new NavItem(
                id: $active->getId(),
                kind: 'category',
                label: $label,
                url: $this->categoryUrl($active),
                openInNewTab: false,
                icon: null,
                hasChildren: false,
                children: [],
            );
        }

        return new Section(id: $id, type: 'category-tree', items: $items, allLink: $allLink);
    }

    /**
     * @param list<TreeItem> $treeItems
     *
     * @return list<NavItem>
     */
    private function mapTreeItems(array $treeItems, bool $showIcons): array
    {
        $normalized = [];

        foreach ($treeItems as $treeItem) {
            $category = $treeItem->getCategory();
            $label = $this->categoryLabel($category);
            if ('' === $label) {
                continue;
            }

            $children = $this->mapTreeItems($treeItem->getChildren(), $showIcons);

            $normalized[] = new NavItem(
                id: $category->getId(),
                kind: 'category',
                label: $label,
                url: $this->categoryUrl($category),
                openInNewTab: false,
                icon: $showIcons ? $this->mediaRef($category->getMedia()) : null,
                hasChildren: [] !== $children,
                children: $children,
            );
        }

        return $normalized;
    }

    private function categoryLabel(CategoryEntity $category): string
    {
        $translated = $category->getTranslation('name');
        if (\is_string($translated) && '' !== trim($translated)) {
            return trim($translated);
        }

        return trim($category->getName() ?? '');
    }

    private function categoryUrl(CategoryEntity $category): ?string
    {
        if ($category instanceof SalesChannelCategoryEntity) {
            $seoUrl = $category->getSeoUrl();
            if (\is_string($seoUrl) && '' !== $seoUrl) {
                return $this->safeHref($seoUrl);
            }
        }

        return null;
    }

    private function clampMaxDepth(mixed $value): int
    {
        $depth = (int) $value;
        if ($depth < 1) {
            return 1;
        }
        if ($depth > 5) {
            return 5;
        }

        return $depth;
    }

    /**
     * @param list<mixed>                $items
     * @param array<string, MediaEntity> $media
     *
     * @return list<NavItem>
     */
    private function normalizeManualItems(array $items, array $media, int $depth = 0): array
    {
        if ($depth > 5) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ('' === $id || '' === $label) {
                continue;
            }

            $children = $this->normalizeManualItems($this->configArray($item['children'] ?? null), $media, $depth + 1);
            $iconMediaId = $this->normalizeUuid($item['iconMediaId'] ?? null);

            $normalized[] = new NavItem(
                id: $id,
                kind: 'link',
                label: $label,
                url: $this->safeHref(isset($item['url']) ? (string) $item['url'] : null),
                openInNewTab: (bool) ($item['openInNewTab'] ?? false),
                icon: $this->mediaRef(null !== $iconMediaId ? ($media[$iconMediaId] ?? null) : null),
                hasChildren: [] !== $children,
                children: $children,
            );
        }

        return $normalized;
    }

    /**
     * @param list<mixed> $items
     *
     * @return list<FooterItem>
     */
    private function normalizeFooterItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            $href = $this->safeHref(isset($item['href']) ? (string) $item['href'] : null);
            if ('' === $id || '' === $label || null === $href) {
                continue;
            }

            $visibility = (string) ($item['visibility'] ?? 'always');
            if (!\in_array($visibility, self::FOOTER_VISIBILITIES, true)) {
                $visibility = 'always';
            }

            $icon = (string) ($item['icon'] ?? '');
            if (!\in_array($icon, self::FOOTER_ICONS, true)) {
                $icon = null;
            }

            $normalized[] = new FooterItem(
                id: $id,
                label: $label,
                href: $href,
                icon: $icon,
                visibility: $visibility,
            );
        }

        return $normalized;
    }

    private function normalizeManualStyle(mixed $style): string
    {
        $style = trim((string) $style);

        return 'uppercase' === $style ? 'uppercase' : 'default';
    }

    /**
     * @param list<Tab> $tabs
     */
    private function tabExists(array $tabs, string $id): bool
    {
        foreach ($tabs as $tab) {
            if ($tab->getId() === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * CMS config is untrusted persisted input. Only valid Shopware UUIDs reach DAL.
     */
    private function normalizeUuid(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value)) {
            return null;
        }

        $id = strtolower(trim((string) $value));
        if ('' === $id || !Uuid::isValid($id)) {
            return null;
        }

        return $id;
    }

    private function safeHref(?string $href): ?string
    {
        $href = trim((string) $href);
        if ('' === $href) {
            return null;
        }

        if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
            return $href;
        }

        if (false === filter_var($href, \FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($href);
        if (!\is_array($parts)) {
            return null;
        }

        $schemeRaw = $parts['scheme'] ?? null;
        $scheme = \is_string($schemeRaw) ? strtolower($schemeRaw) : '';
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if (!\is_string($host) || '' === $host) {
            return null;
        }

        return $href;
    }

    private function mediaRef(?MediaEntity $entity, string $alt = ''): ?MediaRef
    {
        if (null === $entity) {
            return null;
        }

        $url = $entity->getUrl();
        if ('' === $url) {
            return null;
        }

        if ('' === $alt) {
            $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getFileName() ?? ''));
        }

        return new MediaRef($url, $alt);
    }

    /**
     * @param EntitySearchResult<covariant EntityCollection<covariant Entity>>|null $searchResult
     *
     * @return array<string, MediaEntity>
     */
    private function mediaMap(?EntitySearchResult $searchResult): array
    {
        if (null === $searchResult) {
            return [];
        }

        $map = [];
        foreach ($searchResult->getEntities() as $entity) {
            if (!$entity instanceof MediaEntity) {
                continue;
            }

            $map[$entity->getUniqueIdentifier()] = $entity;
        }

        return $map;
    }

    /**
     * @return list<mixed>
     */
    private function configArray(mixed $value): array
    {
        return \is_array($value) ? array_values($value) : [];
    }
}
