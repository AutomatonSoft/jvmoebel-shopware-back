<?php

declare(strict_types=1);

namespace Jv\Cms\Service\Search;

use Jv\Cms\StoreApi\Search\Struct\SearchResultStruct;
use Jv\Cms\StoreApi\Search\Struct\SuggestResultStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductSearchServiceInterface
{
    public function suggest(string $search, int $limit, SalesChannelContext $context): SuggestResultStruct;

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
    ): SearchResultStruct;
}
