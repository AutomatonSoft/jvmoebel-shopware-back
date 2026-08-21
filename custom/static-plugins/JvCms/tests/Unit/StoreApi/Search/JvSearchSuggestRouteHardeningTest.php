<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\StoreApi\Search;

use Jv\Cms\Service\Search\Exception\SearchUnavailableException;
use Jv\Cms\Service\Search\JvProductSearchService;
use Jv\Cms\Service\Search\ProductSearchServiceInterface;
use Jv\Cms\StoreApi\Search\JvSearchSuggestRoute;
use Jv\Cms\StoreApi\Search\Struct\SuggestResultStruct;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class JvSearchSuggestRouteHardeningTest extends TestCase
{
    public function testMissingSearchParameterIsBadRequestNot500(): void
    {
        $route = new JvSearchSuggestRoute(
            $this->searchService(),
            new NullLogger(),
        );

        $this->expectException(BadRequestHttpException::class);

        $route->load(new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testArraySearchParameterIsBadRequest(): void
    {
        $route = new JvSearchSuggestRoute(
            $this->searchService(),
            new NullLogger(),
        );

        $request = new Request([], ['search' => ['x']]);

        $this->expectException(BadRequestHttpException::class);

        $route->load($request, $this->createMock(SalesChannelContext::class));
    }

    public function testSearchUnavailableBecomes503(): void
    {
        $service = $this->searchService();
        $service->method('suggest')->willThrowException(new SearchUnavailableException());

        $route = new JvSearchSuggestRoute($service, new NullLogger());
        $request = new Request([], ['search' => 'sofa']);

        $this->expectException(ServiceUnavailableHttpException::class);

        $route->load($request, $this->createMock(SalesChannelContext::class));
    }

    public function testEmptySearchReturnsEmptySuggestWithoutError(): void
    {
        $service = $this->searchService();
        $service->expects(self::once())
            ->method('suggest')
            ->with('', JvProductSearchService::DEFAULT_SUGGEST_LIMIT, self::anything())
            ->willReturn(new SuggestResultStruct('', [], [], ''));

        $route = new JvSearchSuggestRoute($service, new NullLogger());
        $request = new Request([], ['search' => '   ']);

        $response = $route->load($request, $this->createMock(SalesChannelContext::class));

        self::assertSame([], $response->getObject()->getProducts());
    }

    private function searchService(): ProductSearchServiceInterface&MockObject
    {
        return $this->createMock(ProductSearchServiceInterface::class);
    }
}
