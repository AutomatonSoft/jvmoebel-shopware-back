<?php

declare(strict_types=1);

namespace Jv\Cms\StoreApi\Search;

use Jv\Cms\StoreApi\Search\Struct\SuggestResultStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<SuggestResultStruct>
 */
final class JvSearchSuggestRouteResponse extends StoreApiResponse
{
}
