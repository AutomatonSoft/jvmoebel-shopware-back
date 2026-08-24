<?php

declare(strict_types=1);

namespace Jv\Cms\Service\Search;

use Jv\Cms\Service\Search\Exception\SearchUnavailableException;
use Jv\Cms\StoreApi\Search\Struct\InterpretedFilterStruct;
use Jv\Cms\StoreApi\Search\Struct\SearchResultStruct;
use Jv\Cms\StoreApi\Search\Struct\SuggestMediaStruct;
use Jv\Cms\StoreApi\Search\Struct\SuggestProductPriceStruct;
use Jv\Cms\StoreApi\Search\Struct\SuggestProductStruct;
use Jv\Cms\StoreApi\Search\Struct\SuggestResultStruct;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Runtime product suggest/search for headless storefronts (SPEC-004 / SPEC-006).
 *
 * Uses Shopware's resolved product-search route (paging, facets, sorting, channel visibility)
 * instead of calling ProductListingLoader directly. Suggest maps a short DTO; full search
 * returns flat listing fields (products/total/page/limit/aggregations), not a nested listing.
 */
final class JvProductSearchService implements ProductSearchServiceInterface
{
    public const int DEFAULT_SUGGEST_LIMIT = 10;

    public const int MAX_SUGGEST_LIMIT = 20;

    public const int DEFAULT_PAGE_LIMIT = 24;

    public const int MAX_PAGE_LIMIT = 100;

    public function __construct(
        private readonly QueryFilterInterpreterInterface $interpreter,
        private readonly AbstractProductSearchRoute $productSearchRoute,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function suggest(string $search, int $limit, SalesChannelContext $context): SuggestResultStruct
    {
        $query = trim($search);
        $limit = $this->clampLimit($limit, self::DEFAULT_SUGGEST_LIMIT, 1, self::MAX_SUGGEST_LIMIT);

        $interpretation = $this->safeInterpret($query);
        $filters = $interpretation['filters'];
        $remaining = $interpretation['remainingSearchTerm'];

        if ('' === $query) {
            return new SuggestResultStruct(
                query: '',
                products: [],
                interpretedFilters: $filters,
                remainingSearchTerm: $remaining,
            );
        }

        $listing = $this->loadListing(
            remainingSearchTerm: $remaining,
            originalQuery: $query,
            filters: $filters,
            page: 1,
            limit: $limit,
            context: $context,
            extraOptionIds: [],
            order: null,
        );

        return new SuggestResultStruct(
            query: $query,
            products: $this->mapSuggestProducts($listing, $context),
            interpretedFilters: $filters,
            remainingSearchTerm: $remaining,
        );
    }

    /**
     * @param list<mixed> $extraOptionIds
     */
    public function search(
        string $search,
        int $page,
        int $limit,
        array $extraOptionIds,
        SalesChannelContext $context,
        ?string $order = null,
    ): SearchResultStruct {
        $query = trim($search);
        $page = max(1, $page);
        $limit = $this->clampLimit($limit, self::DEFAULT_PAGE_LIMIT, 1, self::MAX_PAGE_LIMIT);
        $order = null !== $order ? trim($order) : null;
        if ('' === $order) {
            $order = null;
        }

        $interpretation = $this->safeInterpret($query);
        $filters = $interpretation['filters'];
        $remaining = $interpretation['remainingSearchTerm'];

        $listing = $this->loadListing(
            remainingSearchTerm: $remaining,
            originalQuery: $query,
            filters: $filters,
            page: $page,
            limit: $limit,
            context: $context,
            extraOptionIds: $this->sanitizeOptionIds($extraOptionIds),
            order: $order,
        );

        return new SearchResultStruct(
            query: $query,
            interpretedFilters: $filters,
            remainingSearchTerm: $remaining,
            products: array_values($listing->getElements()),
            total: $listing->getTotal(),
            page: $listing->getPage() > 0 ? $listing->getPage() : $page,
            limit: $listing->getLimit() ?? $limit,
            aggregations: $listing->getAggregations(),
        );
    }

    /**
     * @return array{filters: list<InterpretedFilterStruct>, remainingSearchTerm: string}
     */
    private function safeInterpret(string $query): array
    {
        try {
            return $this->interpreter->interpret($query);
        } catch (\Throwable $exception) {
            $this->logger->error('jv-search query interpretation failed', [
                'operation' => 'jv_product_search_interpret',
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return [
                'filters' => [],
                'remainingSearchTerm' => $query,
            ];
        }
    }

    /**
     * @param list<InterpretedFilterStruct> $filters
     * @param list<string>                  $extraOptionIds
     */
    private function loadListing(
        string $remainingSearchTerm,
        string $originalQuery,
        array $filters,
        int $page,
        int $limit,
        SalesChannelContext $context,
        array $extraOptionIds,
        ?string $order,
    ): ProductListingResult {
        // Prefer remaining term after synonym mapping; fall back to the original query.
        $term = '' !== $remainingSearchTerm ? $remainingSearchTerm : $originalQuery;

        $request = new Request();
        $request->request->set('search', $term);
        $request->query->set('search', $term);
        $request->request->set('limit', $limit);
        // Shopware paging uses "p", not "page".
        $request->request->set('p', $page);

        if (null !== $order) {
            $request->request->set('order', $order);
        }

        $propertyIds = $this->collectPropertyIds($filters, $extraOptionIds);
        if ([] !== $propertyIds) {
            // Pipe format understood by Shopware PropertyListingFilterHandler.
            $request->request->set('properties', implode('|', $propertyIds));
        }

        $criteria = new Criteria();
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('seoUrls');

        try {
            $response = $this->productSearchRoute->load($request, $context, $criteria);
        } catch (HttpExceptionInterface $exception) {
            // Client/API errors from Shopware listing pipeline (unknown order, page out of range, …).
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('jv-search listing failed', [
                'operation' => 'jv_product_search',
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new SearchUnavailableException('Product search is temporarily unavailable.', $exception);
        }

        return $response->getListingResult();
    }

    /**
     * @param list<InterpretedFilterStruct> $filters
     * @param list<string>                  $extraOptionIds
     *
     * @return list<string>
     */
    private function collectPropertyIds(array $filters, array $extraOptionIds): array
    {
        $ids = [];
        foreach ($filters as $filter) {
            $optionId = $filter->getOptionId();
            if (Uuid::isValid($optionId)) {
                $ids[$optionId] = true;
            }
        }
        foreach ($extraOptionIds as $optionId) {
            if (Uuid::isValid($optionId)) {
                $ids[$optionId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param list<mixed> $optionIds
     *
     * @return list<string>
     */
    private function sanitizeOptionIds(array $optionIds): array
    {
        $ids = [];
        foreach ($optionIds as $optionId) {
            if (!\is_string($optionId) && !\is_int($optionId)) {
                continue;
            }
            $optionId = trim((string) $optionId);
            if (Uuid::isValid($optionId)) {
                $ids[$optionId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @return list<SuggestProductStruct>
     */
    private function mapSuggestProducts(ProductListingResult $listing, SalesChannelContext $context): array
    {
        $products = [];

        foreach ($listing->getEntities() as $entity) {
            if (!$entity instanceof SalesChannelProductEntity) {
                continue;
            }

            $mapped = $this->mapOneSuggestProduct($entity, $context);
            if (null !== $mapped) {
                $products[] = $mapped;
            }
        }

        return $products;
    }

    private function mapOneSuggestProduct(
        SalesChannelProductEntity $entity,
        SalesChannelContext $context,
    ): ?SuggestProductStruct {
        $id = trim($entity->getId());
        if ('' === $id || !Uuid::isValid($id)) {
            return null;
        }

        $name = trim((string) ($entity->getTranslation('name') ?? $entity->getName() ?? ''));
        if ('' === $name) {
            return null;
        }

        return new SuggestProductStruct(
            id: $id,
            name: $name,
            seoUrl: $this->resolveSeoUrl($entity, $context),
            cover: $this->resolveCover($entity),
            price: $this->resolvePrice($entity, $context),
        );
    }

    /**
     * Canonical, non-deleted PDP SEO URL for the current language + sales channel only.
     */
    private function resolveSeoUrl(SalesChannelProductEntity $product, SalesChannelContext $context): ?string
    {
        $seoUrls = $product->getSeoUrls();
        if (!$seoUrls instanceof SeoUrlCollection || $seoUrls->count() < 1) {
            return null;
        }

        $languageId = $context->getLanguageId();
        $salesChannelId = $context->getSalesChannelId();

        $exact = null;
        $fallback = null;

        foreach ($seoUrls as $seoUrl) {
            if ($seoUrl->getLanguageId() !== $languageId) {
                continue;
            }
            if ('frontend.detail.page' !== $seoUrl->getRouteName()) {
                continue;
            }
            if (true !== $seoUrl->getIsCanonical()) {
                continue;
            }
            if ($seoUrl->getIsDeleted()) {
                continue;
            }

            $urlSalesChannelId = $seoUrl->getSalesChannelId();
            if ($urlSalesChannelId === $salesChannelId) {
                $exact = $seoUrl;
                break;
            }
            // Channel-agnostic fallback only (never another sales channel).
            if (null === $urlSalesChannelId && null === $fallback) {
                $fallback = $seoUrl;
            }
        }

        $chosen = $exact ?? $fallback;
        if (null === $chosen) {
            return null;
        }

        $path = trim($chosen->getSeoPathInfo());
        if ('' === $path || str_contains($path, '://') || str_starts_with($path, '//')) {
            return null;
        }

        return '/'.ltrim($path, '/');
    }

    private function resolveCover(SalesChannelProductEntity $product): ?SuggestMediaStruct
    {
        $cover = $product->getCover();
        if (!$cover instanceof ProductMediaEntity) {
            return null;
        }

        $media = $cover->getMedia();
        if (!$media instanceof MediaEntity) {
            return null;
        }

        $url = trim($media->getUrl());
        if ('' === $url) {
            return null;
        }

        return new SuggestMediaStruct(
            url: $url,
            alt: trim((string) ($media->getTranslation('alt') ?? $media->getAlt() ?? '')),
        );
    }

    /**
     * totalPrice is gross or net depending on tax state — do not treat unit/total as gross/net.
     */
    private function resolvePrice(
        SalesChannelProductEntity $product,
        SalesChannelContext $context,
    ): ?SuggestProductPriceStruct {
        $vars = $product->getVars();
        $calculated = $vars['calculatedPrice'] ?? null;
        if (!$calculated instanceof CalculatedPrice) {
            return null;
        }

        $price = $calculated->getTotalPrice();
        $tax = 0.0;
        foreach ($calculated->getCalculatedTaxes() as $calculatedTax) {
            $tax += $calculatedTax->getTax();
        }

        if (CartPrice::TAX_STATE_GROSS === $context->getTaxState()) {
            $gross = $price;
            $net = $price - $tax;
        } else {
            $net = $price;
            $gross = $price + $tax;
        }

        return new SuggestProductPriceStruct(
            gross: $gross,
            net: $net,
            currencyId: $context->getCurrencyId(),
        );
    }

    /**
     * Below min / invalid → default; above max → max (e.g. suggestLimit 999 → 20, not 10).
     */
    private function clampLimit(int $value, int $default, int $min, int $max): int
    {
        if ($value < $min) {
            return $default;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
