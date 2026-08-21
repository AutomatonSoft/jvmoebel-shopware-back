<?php

declare(strict_types=1);

namespace Jv\Cms\StoreApi\Search\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class SuggestResultStruct extends Struct
{
    /**
     * @param list<SuggestProductStruct>    $products
     * @param list<InterpretedFilterStruct> $interpretedFilters
     */
    public function __construct(
        protected string $query,
        protected array $products,
        protected array $interpretedFilters,
        protected string $remainingSearchTerm,
    ) {
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /** @return list<SuggestProductStruct> */
    public function getProducts(): array
    {
        return $this->products;
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

    public function getApiAlias(): string
    {
        return 'jv_search_suggest_result';
    }
}
