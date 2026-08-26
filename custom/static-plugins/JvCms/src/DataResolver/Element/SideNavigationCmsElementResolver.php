<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Jv\Cms\DataResolver\Element\SideNavigation\FooterItem;
use Jv\Cms\DataResolver\Element\SideNavigation\MediaRef;
use Jv\Cms\DataResolver\Element\SideNavigation\NavItem;
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

/**
 * Resolves CMS element `jv-side-navigation` for the Store API (SPEC-003 / SPEC-005).
 *
 * Config has no tabs. `items` is a category tree of exactly TREE_DEPTH child levels.
 * Storefront typeahead reads this tree; this class does not search categories or products.
 */
final class SideNavigationCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-side-navigation';

    /** Child levels serialized from `rootCategoryId` (L1…L4). L5 is never included. */
    private const int TREE_DEPTH = 4;

    /** Shopware loads rootLevel + depth + 1; 3 hydrates L1–L4, not L5. */
    private const int NAVIGATION_LOADER_DEPTH = self::TREE_DEPTH - 1; // 3

    private const string DEFAULT_SEARCH_PLACEHOLDER = 'Kategorie suchen';

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

    /**
     * Logo media only. Category icons come from NavigationLoader (`category.media`).
     */
    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $logoMedia = $config->get('logoMedia');
        if (null === $logoMedia) {
            return null;
        }

        $id = $this->normalizeUuid($logoMedia->getValue());
        if (null === $id) {
            return null;
        }

        $criteria = new Criteria([$id]);
        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'jv_side_navigation_media_'.$slot->getUniqueIdentifier(),
            MediaDefinition::class,
            $criteria,
        );

        return $criteriaCollection;
    }

    /**
     * Builds `SideNavigationStruct`. Leftover `tabs` / `defaultTabId` in saved config are ignored.
     */
    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $media = $this->mediaMap($result->get('jv_side_navigation_media_'.$slot->getUniqueIdentifier()));
        $salesChannelContext = $resolverContext->getSalesChannelContext();

        $logoLink = $this->safeHref($config->get('logoLink')?->getStringValue()) ?? '/';
        $logoMediaId = $this->normalizeUuid($config->get('logoMedia')?->getValue());
        $logo = $this->mediaRef(null !== $logoMediaId ? ($media[$logoMediaId] ?? null) : null);

        $placeholder = trim((string) ($config->get('searchPlaceholder')?->getValue() ?? ''));
        if ('' === $placeholder) {
            $placeholder = self::DEFAULT_SEARCH_PLACEHOLDER;
        }

        $footerValue = $config->get('footer')?->getValue();
        $footerItemsRaw = [];
        if (\is_array($footerValue) && isset($footerValue['items']) && \is_array($footerValue['items'])) {
            $footerItemsRaw = $footerValue['items'];
        }

        $slot->setData(new SideNavigationStruct(
            logo: $logo,
            logoLink: $logoLink,
            searchPlaceholder: $placeholder,
            items: $this->loadCategoryItems(
                $this->normalizeUuid($config->get('rootCategoryId')?->getValue()),
                (bool) ($config->get('showIcons')?->getValue() ?? true),
                $salesChannelContext,
            ),
            footerItems: $this->normalizeFooterItems($footerItemsRaw),
        ));
    }

    /**
     * Invalid/empty root → [] and the loader is not called (no HTTP 500).
     *
     * @return list<NavItem>
     */
    private function loadCategoryItems(
        ?string $rootId,
        bool $showIcons,
        SalesChannelContext $salesChannelContext,
    ): array {
        if (null === $rootId) {
            return [];
        }

        try {
            $tree = $this->navigationLoader->load(
                $rootId,
                $salesChannelContext,
                $rootId,
                self::NAVIGATION_LOADER_DEPTH,
            );
        } catch (CategoryNotFoundException) {
            // Valid UUID but missing in the sales channel — empty tree, not an exception to the client.
            return [];
        }

        return $this->mapTreeItems($tree->getTree(), $showIcons, self::TREE_DEPTH);
    }

    /**
     * Cuts the tree at TREE_DEPTH even if the loader returned deeper nodes.
     *
     * @param list<TreeItem> $treeItems
     *
     * @return list<NavItem>
     */
    private function mapTreeItems(array $treeItems, bool $showIcons, int $remainingDepth): array
    {
        if ($remainingDepth < 1) {
            return [];
        }

        $normalized = [];

        foreach ($treeItems as $treeItem) {
            $category = $treeItem->getCategory();
            $label = $this->categoryLabel($category);
            if ('' === $label) {
                continue;
            }

            $children = $this->mapTreeItems($treeItem->getChildren(), $showIcons, $remainingDepth - 1);

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

    /**
     * Allow relative `/path` (not `//host`) and http(s) URLs with a host.
     * Rejects javascript:, data:, empty, and anything without a valid host.
     */
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
}
