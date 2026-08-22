<?php

declare(strict_types=1);

namespace Jv\Cms\StoreApi\Search\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Full-search Store API payload (SPEC-004): flat products/total/page/limit/aggregations — not nested listing.
 */
final class SearchResultStruct extends Struct
{
    /**
     * @param list<InterpretedFilterStruct>                              $interpretedFilters
     * @param list<\Shopware\Core\Framework\DataAbstractionLayer\Entity> $products
     */
    public function __construct(
        protected string $query,
        protected array $interpretedFilters,
        protected string $remainingSearchTerm,
        protected array $products,
        protected int $total,
        protected int $page,
        protected int $limit,
        protected AggregationResultCollection $aggregations,
    ) {
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return list<InterpretedFilterStruct>
     */
    public function getInterpretedFilters(): array
    {
        return $this->interpretedFilters;
    }

    public function getRemainingSearchTerm(): string
    {
        return $this->remainingSearchTerm;
    }

    /**
     * @return list<\Shopware\Core\Framework\DataAbstractionLayer\Entity>
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getAggregations(): AggregationResultCollection
    {
        return $this->aggregations;
    }

    public function getApiAlias(): string
    {
        return 'jv_search_result';
    }
}
