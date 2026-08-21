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
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cross-category product search/suggest via Shopware OpenSearch-aware listing loader.
 *
 * Mapping never throws: broken entities are skipped.
 * Infrastructure failures become SearchUnavailableException (HTTP 503 at the route).
 */
final class JvProductSearchService implements ProductSearchServiceInterface
{
    public const int DEFAULT_SUGGEST_LIMIT = 10;

    public const int MAX_SUGGEST_LIMIT = 20;

    public const int DEFAULT_PAGE_LIMIT = 24;

    public const int MAX_PAGE_LIMIT = 100;

    public function __construct(
        private readonly QueryFilterInterpreterInterface $interpreter,
        private readonly ProductSearchBuilderInterface $searchBuilder,
        private readonly ProductListingLoader $productListingLoader,
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

        $listing = $this->loadListing($query, $remaining, $filters, 1, $limit, $context);

        return new SuggestResultStruct(
            query: $query,
            products: $this->mapSuggestProducts($listing),
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
    ): SearchResultStruct {
        $query = trim($search);
        $page = max(1, $page);
        $limit = $this->clampLimit($limit, self::DEFAULT_PAGE_LIMIT, 1, self::MAX_PAGE_LIMIT);

        $interpretation = $this->safeInterpret($query);
        $filters = $interpretation['filters'];
        $remaining = $interpretation['remainingSearchTerm'];

        $listing = $this->loadListing(
            $query,
            $remaining,
            $filters,
            $page,
            $limit,
            $context,
            $this->sanitizeOptionIds($extraOptionIds),
        );

        return new SearchResultStruct(
            query: $query,
            interpretedFilters: $filters,
            remainingSearchTerm: $remaining,
            listing: $listing,
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
        string $originalQuery,
        string $remainingSearchTerm,
        array $filters,
        int $page,
        int $limit,
        SalesChannelContext $context,
        array $extraOptionIds = [],
    ): ProductListingResult {
        $term = '' !== $remainingSearchTerm ? $remainingSearchTerm : $originalQuery;

        $criteria = new Criteria();
        $criteria->setTitle('jv-search');
        $criteria->setLimit($limit);
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_SEARCH),
        );
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('seoUrls');

        $this->applyPropertyFilters($criteria, $filters, $extraOptionIds);

        $request = new Request();
        $request->request->set('search', $term);
        $request->query->set('search', $term);

        try {
            if ('' !== $term) {
                $this->searchBuilder->build($request, $criteria, $context);
            }

            $result = $this->productListingLoader->load($criteria, $context);
        } catch (\Throwable $exception) {
            $this->logger->error('jv-search listing failed', [
                'operation' => 'jv_product_search',
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new SearchUnavailableException('Product search is temporarily unavailable.', $exception);
        }

        $listing = ProductListingResult::createFrom($result);
        $listing->setPage($page);
        $listing->setLimit($limit);

        return $listing;
    }

    /**
     * @param list<InterpretedFilterStruct> $filters
     * @param list<string>                  $extraOptionIds
     */
    private function applyPropertyFilters(Criteria $criteria, array $filters, array $extraOptionIds = []): void
    {
        $optionIds = [];
        foreach ($filters as $filter) {
            $optionId = $filter->getOptionId();
            if (Uuid::isValid($optionId)) {
                $optionIds[$optionId] = true;
            }
        }

        foreach ($extraOptionIds as $optionId) {
            if (Uuid::isValid($optionId)) {
                $optionIds[$optionId] = true;
            }
        }

        if ([] === $optionIds) {
            return;
        }

        $groupFilters = [];
        foreach (array_keys($optionIds) as $optionId) {
            $groupFilters[] = new OrFilter([
                new EqualsAnyFilter('product.propertyIds', [$optionId]),
                new EqualsAnyFilter('product.optionIds', [$optionId]),
            ]);
        }

        $criteria->addFilter(new AndFilter($groupFilters));
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
    private function mapSuggestProducts(ProductListingResult $listing): array
    {
        $products = [];

        foreach ($listing->getEntities() as $entity) {
            if (!$entity instanceof SalesChannelProductEntity) {
                continue;
            }

            $mapped = $this->mapOneSuggestProduct($entity);
            if (null !== $mapped) {
                $products[] = $mapped;
            }
        }

        return $products;
    }

    private function mapOneSuggestProduct(SalesChannelProductEntity $entity): ?SuggestProductStruct
    {
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
            seoUrl: $this->resolveSeoUrl($entity),
            cover: $this->resolveCover($entity),
            price: $this->resolvePrice($entity),
        );
    }

    private function resolveSeoUrl(SalesChannelProductEntity $product): ?string
    {
        $seoUrls = $product->getSeoUrls();
        if (!$seoUrls instanceof SeoUrlCollection || $seoUrls->count() < 1) {
            return null;
        }

        $first = $seoUrls->first();
        if (!$first instanceof SeoUrlEntity) {
            return null;
        }

        $path = trim($first->getSeoPathInfo());
        if ('' === $path) {
            return null;
        }

        // Reject scheme-relative / absolute injection in stored seo paths.
        if (str_contains($path, '://') || str_starts_with($path, '//')) {
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

    private function resolvePrice(SalesChannelProductEntity $product): ?SuggestProductPriceStruct
    {
        $vars = $product->getVars();
        $calculated = $vars['calculatedPrice'] ?? null;
        if (!$calculated instanceof CalculatedPrice) {
            return null;
        }

        return new SuggestProductPriceStruct(
            gross: $calculated->getTotalPrice(),
            net: $calculated->getUnitPrice(),
            currencyId: null,
        );
    }

    private function clampLimit(int $value, int $default, int $min, int $max): int
    {
        if ($value < $min || $value > $max) {
            return $default;
        }

        return $value;
    }
}
