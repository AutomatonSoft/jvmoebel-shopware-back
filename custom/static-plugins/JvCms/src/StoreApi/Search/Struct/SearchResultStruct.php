<?php

declare(strict_types=1);

namespace Jv\Cms\StoreApi\Search\Struct;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Struct\Struct;

final class SearchResultStruct extends Struct
{
    /**
     * @param list<InterpretedFilterStruct> $interpretedFilters
     */
    public function __construct(
        protected string $query,
        protected array $interpretedFilters,
        protected string $remainingSearchTerm,
        protected ProductListingResult $listing,
    ) {
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /** @return list<InterpretedFilterStruct> */
    public function getInterpretedFilters(): array
    {
        return $this->interpretedFilters;
    }

    public function getRemainingSearchTerm(): string
    {
        return $this->remainingSearchTerm;
    }

    public function getListing(): ProductListingResult
    {
        return $this->listing;
    }

    /** @return list<\Shopware\Core\Framework\DataAbstractionLayer\Entity> */
    public function getProducts(): array
    {
        return array_values($this->listing->getElements());
    }

    public function getTotal(): int
    {
        return $this->listing->getTotal();
    }

    public function getPage(): int
    {
        return $this->listing->getPage();
    }

    public function getLimit(): ?int
    {
        return $this->listing->getLimit();
    }

    public function getAggregations(): AggregationResultCollection
    {
        return $this->listing->getAggregations();
    }

    public function getApiAlias(): string
    {
        return 'jv_search_result';
    }
}
