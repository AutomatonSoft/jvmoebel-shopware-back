<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\Service\Search;

use Jv\Cms\Service\Search\Exception\SearchUnavailableException;
use Jv\Cms\Service\Search\JvProductSearchService;
use Jv\Cms\Service\Search\QueryFilterInterpreter;
use Jv\Cms\Service\Search\QueryFilterInterpreterInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;

final class JvProductSearchServiceHardeningTest extends TestCase
{
    public function testListingFailureBecomesSearchUnavailableException(): void
    {
        $productSearchRoute = $this->createMock(AbstractProductSearchRoute::class);
        $productSearchRoute->expects(self::once())
            ->method('load')
            ->willThrowException(new \RuntimeException('opensearch down'));

        $service = new JvProductSearchService(
            new QueryFilterInterpreter([]),
            $productSearchRoute,
            new NullLogger(),
        );

        $this->expectException(SearchUnavailableException::class);

        $service->suggest('sofa', 10, $this->salesChannelContext());
    }

    public function testSuggestSkipsProductsWithoutNameAndAllowsMissingPrice(): void
    {
        $withName = new SalesChannelProductEntity();
        $withName->setUniqueIdentifier(Uuid::randomHex());
        $withName->setId($withName->getUniqueIdentifier());
        $withName->setName('Sofa A');
        $withName->setCalculatedPrice(new CalculatedPrice(
            10.0,
            10.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));

        $withoutName = new SalesChannelProductEntity();
        $withoutName->setUniqueIdentifier(Uuid::randomHex());
        $withoutName->setId($withoutName->getUniqueIdentifier());
        $withoutName->setName('');

        $withoutPrice = new SalesChannelProductEntity();
        $withoutPrice->setUniqueIdentifier(Uuid::randomHex());
        $withoutPrice->setId($withoutPrice->getUniqueIdentifier());
        $withoutPrice->setName('Sofa B');

        $productSearchRoute = $this->createMock(AbstractProductSearchRoute::class);
        $productSearchRoute->expects(self::once())
            ->method('load')
            ->willReturn($this->searchResponse(new ProductCollection([$withName, $withoutName, $withoutPrice]), 3));

        $service = new JvProductSearchService(
            new QueryFilterInterpreter([]),
            $productSearchRoute,
            new NullLogger(),
        );

        $result = $service->suggest('sofa', 10, $this->salesChannelContext());

        self::assertCount(2, $result->getProducts());
        self::assertSame('Sofa A', $result->getProducts()[0]->getName());
        self::assertNotNull($result->getProducts()[0]->getPrice());
        self::assertSame('Sofa B', $result->getProducts()[1]->getName());
        self::assertNull($result->getProducts()[1]->getPrice());
    }

    public function testInterpreterFailureFallsBackToFullTextTerm(): void
    {
        $interpreter = $this->createMock(QueryFilterInterpreterInterface::class);
        $interpreter->method('interpret')->willThrowException(new \RuntimeException('dictionary boom'));

        $productSearchRoute = $this->createMock(AbstractProductSearchRoute::class);
        $productSearchRoute->expects(self::once())
            ->method('load')
            ->with(
                self::callback(static function (Request $request): bool {
                    return 'braunes sofa' === $request->request->get('search');
                }),
                self::anything(),
                self::anything(),
            )
            ->willReturn($this->searchResponse(new ProductCollection(), 0));

        $service = new JvProductSearchService(
            $interpreter,
            $productSearchRoute,
            new NullLogger(),
        );

        $result = $service->suggest('braunes sofa', 10, $this->salesChannelContext());

        self::assertSame('braunes sofa', $result->getRemainingSearchTerm());
        self::assertSame([], $result->getInterpretedFilters());
    }

    public function testInvalidExtraOptionIdsAreIgnored(): void
    {
        $validOptionId = Uuid::randomHex();

        $productSearchRoute = $this->createMock(AbstractProductSearchRoute::class);
        $productSearchRoute->expects(self::once())
            ->method('load')
            ->with(
                self::callback(static function (Request $request) use ($validOptionId): bool {
                    return $validOptionId === $request->request->get('properties');
                }),
                self::anything(),
                self::anything(),
            )
            ->willReturn($this->searchResponse(new ProductCollection(), 0));

        $service = new JvProductSearchService(
            new QueryFilterInterpreter([]),
            $productSearchRoute,
            new NullLogger(),
        );

        /** @var list<mixed> $junk */
        $junk = ['not-a-uuid', ['nested'], $validOptionId];

        $result = $service->search(
            'sofa',
            1,
            24,
            $junk,
            $this->salesChannelContext(),
        );

        self::assertSame('sofa', $result->getQuery());
        self::assertSame(0, $result->getTotal());
        self::assertSame([], $result->getProducts());
        self::assertCount(0, $result->getAggregations());
    }

    public function testSuggestLimitAboveMaxIsClampedToTwenty(): void
    {
        $productSearchRoute = $this->createMock(AbstractProductSearchRoute::class);
        $productSearchRoute->expects(self::once())
            ->method('load')
            ->with(
                self::callback(static function (Request $request): bool {
                    return 20 === (int) $request->request->get('limit');
                }),
                self::anything(),
                self::anything(),
            )
            ->willReturn($this->searchResponse(new ProductCollection(), 0));

        $service = new JvProductSearchService(
            new QueryFilterInterpreter([]),
            $productSearchRoute,
            new NullLogger(),
        );

        $service->suggest('sofa', 999, $this->salesChannelContext());
    }

    public function testSearchPassesOrderAndPageAsP(): void
    {
        $productSearchRoute = $this->createMock(AbstractProductSearchRoute::class);
        $productSearchRoute->expects(self::once())
            ->method('load')
            ->with(
                self::callback(static function (Request $request): bool {
                    return 'name-asc' === $request->request->get('order')
                        && 2 === (int) $request->request->get('p')
                        && 24 === (int) $request->request->get('limit');
                }),
                self::anything(),
                self::anything(),
            )
            ->willReturn($this->searchResponse(new ProductCollection(), 0, page: 2, limit: 24));

        $service = new JvProductSearchService(
            new QueryFilterInterpreter([]),
            $productSearchRoute,
            new NullLogger(),
        );

        $result = $service->search('sofa', 2, 24, [], $this->salesChannelContext(), 'name-asc');

        self::assertSame(2, $result->getPage());
        self::assertSame(24, $result->getLimit());
    }

    private function searchResponse(
        ProductCollection $products,
        int $total,
        int $page = 1,
        int $limit = 10,
    ): ProductSearchRouteResponse {
        $listing = new ProductListingResult(
            'product',
            $total,
            $products,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
        $listing->setPage($page);
        $listing->setLimit($limit);

        return new ProductSearchRouteResponse($listing);
    }

    private function salesChannelContext(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setUniqueIdentifier($salesChannel->getId());

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn($salesChannel->getId());
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getLanguageId')->willReturn(Uuid::randomHex());
        $context->method('getCurrencyId')->willReturn(Uuid::randomHex());
        $context->method('getTaxState')->willReturn(CartPrice::TAX_STATE_GROSS);

        return $context;
    }
}
