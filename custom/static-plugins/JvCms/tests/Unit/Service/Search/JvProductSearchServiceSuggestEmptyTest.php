<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\Service\Search;

use Jv\Cms\Service\Search\JvProductSearchService;
use Jv\Cms\Service\Search\QueryFilterInterpreter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class JvProductSearchServiceSuggestEmptyTest extends TestCase
{
    public function testEmptySuggestDoesNotCallListingLoader(): void
    {
        $searchBuilder = $this->createMock(ProductSearchBuilderInterface::class);
        $searchBuilder->expects(self::never())->method('build');

        $listingLoader = $this->createMock(ProductListingLoader::class);
        $listingLoader->expects(self::never())->method('load');

        $service = new JvProductSearchService(
            new QueryFilterInterpreter([]),
            $searchBuilder,
            $listingLoader,
            new NullLogger(),
        );

        $result = $service->suggest(
            '   ',
            10,
            $this->createMock(SalesChannelContext::class),
        );

        self::assertSame('', $result->getQuery());
        self::assertSame([], $result->getProducts());
        self::assertSame('jv_search_suggest_result', $result->getApiAlias());
    }
}
