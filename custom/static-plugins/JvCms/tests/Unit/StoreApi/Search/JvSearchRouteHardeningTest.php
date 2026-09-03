<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\StoreApi\Search;

use Jv\Cms\Service\Search\Exception\SearchUnavailableException;
use Jv\Cms\Service\Search\JvProductSearchService;
use Jv\Cms\Service\Search\ProductSearchServiceInterface;
use Jv\Cms\StoreApi\Search\JvSearchRoute;
use Jv\Cms\StoreApi\Search\Struct\SearchResultStruct;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class JvSearchRouteHardeningTest extends TestCase
{
    public function testMissingSearchParameterIsBadRequest(): void
    {
        $route = new JvSearchRoute($this->searchService(), new NullLogger());

        $this->expectException(BadRequestHttpException::class);

        $route->load(new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testEmptySearchIsBadRequest(): void
    {
        $route = new JvSearchRoute($this->searchService(), new NullLogger());
        $request = new Request([], ['search' => '   ']);

        $this->expectException(BadRequestHttpException::class);

        $route->load($request, $this->createMock(SalesChannelContext::class));
    }

    public function testSearchUnavailableBecomes503(): void
    {
        $service = $this->searchService();
        $service->method('search')->willThrowException(new SearchUnavailableException());

        $route = new JvSearchRoute($service, new NullLogger());
        $request = new Request([], ['search' => 'sofa']);

        $this->expectException(ServiceUnavailableHttpException::class);

        $route->load($request, $this->createMock(SalesChannelContext::class));
    }

    public function testOrderIsForwardedToService(): void
    {
        $service = $this->searchService();
        $service->expects(self::once())
            ->method('search')
            ->with(
                'sofa',
                2,
                JvProductSearchService::DEFAULT_PAGE_LIMIT,
                [],
                self::anything(),
                'name-asc',
            )
            ->willReturn(new SearchResultStruct(
                query: 'sofa',
                interpretedFilters: [],
                remainingSearchTerm: 'sofa',
                products: [],
                total: 0,
                page: 2,
                limit: JvProductSearchService::DEFAULT_PAGE_LIMIT,
                aggregations: new AggregationResultCollection(),
            ));

        $route = new JvSearchRoute($service, new NullLogger());
        $request = new Request([], [
            'search' => 'sofa',
            'page' => 2,
            'order' => 'name-asc',
        ]);

        $response = $route->load($request, $this->createMock(SalesChannelContext::class));

        self::assertSame(2, $response->getObject()->getPage());
        self::assertSame('jv_search_result', $response->getObject()->getApiAlias());
    }

    private function searchService(): ProductSearchServiceInterface&MockObject
    {
        return $this->createMock(ProductSearchServiceInterface::class);
    }
}
