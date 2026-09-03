<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves CMS element `jv-product-grid` for the Store API (platform SPEC-009 / backend SPEC-011).
 *
 * Persisted config is untrusted. Invalid product UUIDs never reach Criteria.
 * Incomplete products and unsafe URLs are skipped. Bad config must not HTTP 500.
 *
 * Serialized `data.products[]` follows the nested cms-contract shape (`translated`, `cover.media`,
 * `calculatedPrice`, `ratingAverage`) consumed by Next.js `parseCmsProductGridData`.
 */
final class ProductGridCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-product-grid';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $entries = $this->productConfigEntries($slot->getFieldConfig()->get('products')?->getValue());
        $productIds = [];

        foreach ($entries as $entry) {
            $id = $this->normalizeUuid($entry['item']['productId'] ?? null);
            if (null !== $id) {
                $productIds[$id] = $id;
            }
        }

        if ([] === $productIds) {
            return null;
        }

        $criteria = new Criteria(array_values($productIds));
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('seoUrls');
        $criteria->addAssociation('productReviews');

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'jv_product_grid_products_'.$slot->getUniqueIdentifier(),
            ProductDefinition::class,
            $criteria,
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $context = $resolverContext->getSalesChannelContext();
        $products = $this->productMap($result->get('jv_product_grid_products_'.$slot->getUniqueIdentifier()));

        $slot->setData(new ProductGridStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            eyebrow: $this->optionalString($config->get('eyebrow')?->getValue()),
            locale: $this->resolveLocale($context),
            currency: $this->resolveCurrency($context),
            products: $this->normalizeProducts($config->get('products')?->getValue(), $products, $context),
            viewAll: $this->normalizeViewAll($config->get('viewAll')?->getValue()),
        ));
    }

    /**
     * @param array<string, SalesChannelProductEntity> $products
     *
     * @return list<ProductGridProductStruct>
     */
    private function normalizeProducts(mixed $value, array $products, SalesChannelContext $context): array
    {
        $entries = $this->productConfigEntries($value);

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

            $productId = $this->normalizeUuid($item['productId'] ?? null);
            if (null === $productId || isset($seenIds[$productId])) {
                continue;
            }

            $product = $products[$productId] ?? null;
            if (!$product instanceof SalesChannelProductEntity) {
                continue;
            }

            $name = $this->requiredString($product->getTranslation('name') ?? $product->getName());
            $url = $this->resolveProductUrl($product, $context);
            $image = $this->resolveImage($product, $name);
            $unitPrice = $this->resolveUnitPrice($product);
            if ('' === $name || null === $url || null === $image || null === $unitPrice) {
                continue;
            }

            $seenIds[$productId] = true;

            $normalized[] = new ProductGridProductStruct(
                id: $productId,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                url: $url,
                translated: new ProductGridProductTranslatedStruct(
                    name: $name,
                    description: $this->optionalString($product->getTranslation('description') ?? $product->getDescription()),
                ),
                cover: new ProductGridCoverStruct($image),
                calculatedPrice: $this->resolveCalculatedPrice($product, $unitPrice),
                badge: $this->optionalString($item['badge'] ?? null),
                ratingAverage: $this->resolveRating($product),
                reviewCount: $this->resolveReviewCount($product),
            );
        }

        return $normalized;
    }

    private function normalizeViewAll(mixed $value): ?ProductGridLinkStruct
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        $url = $this->safeProductGridHref(\is_string($value['url'] ?? null) ? $value['url'] : null);
        if ('' === $label || null === $url) {
            return null;
        }

        return new ProductGridLinkStruct($label, $url);
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function productConfigEntries(mixed $value): array
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

    private function resolveLocale(SalesChannelContext $context): string
    {
        return $context->getLanguageInfo()->localeCode;
    }

    private function resolveCurrency(SalesChannelContext $context): string
    {
        return $context->getCurrency()->getIsoCode();
    }

    private function resolveProductUrl(SalesChannelProductEntity $product, SalesChannelContext $context): ?string
    {
        $seoUrls = $product->getSeoUrls();
        if (!$seoUrls instanceof SeoUrlCollection) {
            return null;
        }

        $salesChannelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        // Prefer canonical SEO path for the active sales channel + language.
        $canonical = $this->pickSeoUrl($seoUrls, $salesChannelId, $languageId, true);
        if (null !== $canonical) {
            return $canonical;
        }

        return $this->pickSeoUrl($seoUrls, $salesChannelId, $languageId, false);
    }

    private function pickSeoUrl(
        SeoUrlCollection $seoUrls,
        string $salesChannelId,
        string $languageId,
        bool $canonicalOnly,
    ): ?string {
        foreach ($seoUrls as $seoUrl) {
            if ($seoUrl->getSalesChannelId() !== $salesChannelId || $seoUrl->getLanguageId() !== $languageId) {
                continue;
            }

            if ($canonicalOnly && true !== $seoUrl->getIsCanonical()) {
                continue;
            }

            $path = trim($seoUrl->getSeoPathInfo());
            if ('' === $path) {
                continue;
            }

            return $this->safeProductGridHref('/'.ltrim($path, '/'));
        }

        return null;
    }

    private function resolveImage(SalesChannelProductEntity $product, string $fallbackAlt): ?ProductGridMediaStruct
    {
        $cover = $product->getCover();
        $media = $cover?->getMedia();
        if (!$media instanceof MediaEntity) {
            return null;
        }

        $url = $media->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = trim((string) ($media->getTranslation('alt') ?? $media->getAlt() ?? ''));
        if ('' === $alt) {
            $alt = $fallbackAlt;
        }

        return new ProductGridMediaStruct($url, $alt);
    }

    private function resolveUnitPrice(SalesChannelProductEntity $product): ?float
    {
        $unitPrice = $product->getCalculatedPrice()->getUnitPrice();
        if (!is_finite($unitPrice)) {
            return null;
        }

        return $unitPrice;
    }

    private function resolveCalculatedPrice(SalesChannelProductEntity $product, float $unitPrice): ProductGridCalculatedPriceStruct
    {
        $listPrice = $this->resolvePreviousPrice($product, $unitPrice);

        return new ProductGridCalculatedPriceStruct(
            unitPrice: $unitPrice,
            listPrice: null !== $listPrice ? new ProductGridListPriceStruct($listPrice) : null,
        );
    }

    private function resolvePreviousPrice(SalesChannelProductEntity $product, float $unitPrice): ?float
    {
        $listPrice = $product->getCalculatedPrice()->getListPrice();
        if (!$listPrice instanceof ListPrice) {
            return null;
        }

        $price = $listPrice->getPrice();
        if (!is_finite($price) || $price <= $unitPrice) {
            return null;
        }

        return $price;
    }

    private function resolveRating(SalesChannelProductEntity $product): ?float
    {
        $rating = $product->getRatingAverage();
        if (null === $rating || !is_finite($rating)) {
            return null;
        }

        return $rating;
    }

    private function resolveReviewCount(SalesChannelProductEntity $product): ?int
    {
        $reviews = $product->getProductReviews();
        if (null === $reviews) {
            return null;
        }

        return $reviews->count();
    }

    /**
     * Root-relative `/path` (not `//…`) or absolute http(s) with a host.
     * Same rules as other JvCms menu/section href helpers.
     */
    private function safeProductGridHref(?string $href): ?string
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
     * @return array<string, SalesChannelProductEntity>
     */
    private function productMap(?EntitySearchResult $searchResult): array
    {
        if (null === $searchResult) {
            return [];
        }

        $entities = $searchResult->getEntities();
        if (!$entities instanceof ProductCollection) {
            return [];
        }

        $map = [];
        foreach ($entities as $entity) {
            if (!$entity instanceof SalesChannelProductEntity) {
                continue;
            }

            $map[$entity->getUniqueIdentifier()] = $entity;
        }

        return $map;
    }
}
