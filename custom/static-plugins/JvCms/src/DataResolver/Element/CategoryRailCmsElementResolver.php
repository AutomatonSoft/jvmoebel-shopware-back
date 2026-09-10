<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves CMS element `jv-category-rail` for the Store API (platform SPEC-012 / backend SPEC-015).
 *
 * Persisted config is untrusted. Invalid UUIDs never reach Criteria.
 * Unsafe URLs and incomplete cards are skipped. Bad config must not HTTP 500.
 */
final class CategoryRailCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-category-rail';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $entries = $this->categoryConfigEntries($slot->getFieldConfig()->get('categories')?->getValue());
        $categoryIds = [];
        $mediaIds = [];

        foreach ($entries as $entry) {
            $categoryId = $this->normalizeUuid($entry['item']['categoryId'] ?? null);
            if (null !== $categoryId) {
                $categoryIds[$categoryId] = $categoryId;
            }

            $mediaId = $this->normalizeUuid($entry['item']['imageMedia'] ?? null);
            if (null !== $mediaId) {
                $mediaIds[$mediaId] = $mediaId;
            }
        }

        if ([] === $categoryIds && [] === $mediaIds) {
            return null;
        }

        $criteriaCollection = new CriteriaCollection();
        $slotKey = $slot->getUniqueIdentifier();

        if ([] !== $categoryIds) {
            $criteria = new Criteria(array_values($categoryIds));
            $criteria->addAssociation('media');
            $criteria->addAssociation('seoUrls');
            $criteriaCollection->add(
                'jv_category_rail_categories_'.$slotKey,
                CategoryDefinition::class,
                $criteria,
            );
        }

        if ([] !== $mediaIds) {
            $criteria = new Criteria(array_values($mediaIds));
            $criteriaCollection->add(
                'jv_category_rail_media_'.$slotKey,
                MediaDefinition::class,
                $criteria,
            );
        }

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $slotKey = $slot->getUniqueIdentifier();
        $context = $resolverContext->getSalesChannelContext();

        $slot->setData(new CategoryRailStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            eyebrow: $this->optionalString($config->get('eyebrow')?->getValue()),
            description: $this->optionalString($config->get('description')?->getValue()),
            layout: $this->normalizeLayout($config->get('layout')?->getValue()),
            categories: $this->normalizeCategories(
                $config->get('categories')?->getValue(),
                $this->categoryMap($result->get('jv_category_rail_categories_'.$slotKey)),
                $this->mediaMap($result->get('jv_category_rail_media_'.$slotKey)),
                $context,
            ),
            viewAll: $this->normalizeViewAll($config->get('viewAll')?->getValue()),
        ));
    }

    /**
     * @param array<string, CategoryEntity> $categories
     * @param array<string, MediaEntity>    $media
     *
     * @return list<CategoryRailItemStruct>
     */
    private function normalizeCategories(
        mixed $value,
        array $categories,
        array $media,
        SalesChannelContext $context,
    ): array {
        $entries = $this->categoryConfigEntries($value);

        usort($entries, function (array $a, array $b): int {
            $positionA = $this->resolvePosition($a['item']['position'] ?? null, $a['index']);
            $positionB = $this->resolvePosition($b['item']['position'] ?? null, $b['index']);
            if ($positionA !== $positionB) {
                return $positionA <=> $positionB;
            }

            return $a['index'] <=> $b['index'];
        });

        $normalized = [];
        $seenIds = [];

        foreach ($entries as $entry) {
            $item = $entry['item'];
            $originalIndex = $entry['index'];

            $categoryId = $this->normalizeUuid($item['categoryId'] ?? null);
            $category = null !== $categoryId ? ($categories[$categoryId] ?? null) : null;

            $manualLabel = $this->requiredString($item['label'] ?? null);
            $label = '' !== $manualLabel ? $manualLabel : $this->categoryLabel($category);
            if ('' === $label) {
                continue;
            }

            $manualUrlRaw = \is_string($item['url'] ?? null) ? trim($item['url']) : '';
            if ('' !== $manualUrlRaw) {
                $url = $this->safeCategoryRailHref($manualUrlRaw);
                if (null === $url) {
                    continue;
                }
            } else {
                $url = $this->resolveCategoryUrl($category, $context);
                if (null === $url) {
                    continue;
                }
            }

            $imageMediaId = $this->normalizeUuid($item['imageMedia'] ?? null);
            $imageEntity = null !== $imageMediaId ? ($media[$imageMediaId] ?? null) : null;
            $image = $this->resolveImage($imageEntity, $category?->getMedia(), $label);
            if (null === $image) {
                continue;
            }

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : $label.'-'.$originalIndex;
            if (isset($seenIds[$id])) {
                $id = $id.'-'.$originalIndex;
            }
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;

            $normalized[] = new CategoryRailItemStruct(
                id: $id,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                label: $label,
                url: $url,
                image: $image,
            );
        }

        return $normalized;
    }

    private function normalizeViewAll(mixed $value): ?CategoryRailLinkStruct
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        $url = $this->safeCategoryRailHref(\is_string($value['url'] ?? null) ? $value['url'] : null);
        if ('' === $label || null === $url) {
            return null;
        }

        return new CategoryRailLinkStruct($label, $url);
    }

    private function normalizeLayout(mixed $value): string
    {
        return 'grid' === $this->requiredString($value) ? 'grid' : 'rail';
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function categoryConfigEntries(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        if (!array_is_list($value)) {
            $value = array_values($value);
        }

        $entries = [];
        foreach ($value as $index => $item) {
            if (!\is_array($item)) {
                continue;
            }

            $entries[] = [
                'index' => $index,
                'item' => $item,
            ];
        }

        return $entries;
    }

    private function resolvePosition(mixed $value, int $originalIndex): int
    {
        if (\is_int($value) || \is_float($value)) {
            $position = (float) $value;
            if (is_finite($position)) {
                return (int) $position;
            }
        }

        return $originalIndex;
    }

    private function categoryLabel(?CategoryEntity $category): string
    {
        if (!$category instanceof CategoryEntity) {
            return '';
        }

        $translated = $category->getTranslation('name');
        if (\is_string($translated) && '' !== trim($translated)) {
            return trim($translated);
        }

        return trim($category->getName() ?? '');
    }

    private function resolveCategoryUrl(?CategoryEntity $category, SalesChannelContext $context): ?string
    {
        if (!$category instanceof CategoryEntity) {
            return null;
        }

        $seoUrls = $category->getSeoUrls();
        if (!$seoUrls instanceof SeoUrlCollection) {
            return null;
        }

        $salesChannelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $canonical = $this->pickCategorySeoUrl($seoUrls, $salesChannelId, $languageId, true);
        if (null !== $canonical) {
            return $canonical;
        }

        return $this->pickCategorySeoUrl($seoUrls, $salesChannelId, $languageId, false);
    }

    private function pickCategorySeoUrl(
        SeoUrlCollection $seoUrls,
        string $salesChannelId,
        string $languageId,
        bool $canonicalOnly,
    ): ?string {
        foreach ($seoUrls as $seoUrl) {
            if ($seoUrl->getSalesChannelId() !== $salesChannelId || $seoUrl->getLanguageId() !== $languageId) {
                continue;
            }

            if ('frontend.navigation.page' !== $seoUrl->getRouteName()) {
                continue;
            }

            if ($canonicalOnly && true !== $seoUrl->getIsCanonical()) {
                continue;
            }

            $path = trim($seoUrl->getSeoPathInfo());
            if ('' === $path) {
                continue;
            }

            return $this->safeCategoryRailHref('/'.ltrim($path, '/'));
        }

        return null;
    }

    private function resolveImage(?MediaEntity $primary, ?MediaEntity $fallback, string $label): ?CategoryRailMediaStruct
    {
        foreach ([$primary, $fallback] as $entity) {
            if (!$entity instanceof MediaEntity) {
                continue;
            }

            $url = $entity->getUrl();
            if ('' === $url) {
                continue;
            }

            $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getAlt() ?? ''));
            if ('' === $alt) {
                $alt = trim((string) ($entity->getTranslated()['title'] ?? $entity->getTitle() ?? ''));
            }
            if ('' === $alt) {
                $alt = $label;
            }

            return new CategoryRailMediaStruct($url, $alt);
        }

        return null;
    }

    /**
     * Root-relative `/path` (not `//…`) or absolute http(s) with a host.
     */
    private function safeCategoryRailHref(?string $href): ?string
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

    private function requiredString(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function optionalString(mixed $value): ?string
    {
        $string = $this->requiredString($value);

        return '' === $string ? null : $string;
    }

    /**
     * @param EntitySearchResult<covariant EntityCollection<covariant Entity>>|null $searchResult
     *
     * @return array<string, CategoryEntity>
     */
    private function categoryMap(?EntitySearchResult $searchResult): array
    {
        if (null === $searchResult) {
            return [];
        }

        $entities = $searchResult->getEntities();
        if (!$entities instanceof CategoryCollection) {
            return [];
        }

        $map = [];
        foreach ($entities as $entity) {
            $map[$entity->getUniqueIdentifier()] = $entity;
        }

        return $map;
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
