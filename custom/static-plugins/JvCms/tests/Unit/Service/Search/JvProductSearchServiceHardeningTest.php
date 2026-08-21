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
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class JvProductSearchServiceHardeningTest extends TestCase
{
    public function testListingFailureBecomesSearchUnavailableException(): void
    {
        $searchBuilder = $this->createMock(ProductSearchBuilderInterface::class);
        $searchBuilder->expects(self::once())->method('build');

        $listingLoader = $this->createMock(ProductListingLoader::class);
        $listingLoader->expects(self::once())
            ->method('load')
            ->willThrowException(new \RuntimeException('opensearch down'));

        $service = new JvProductSearchService(
            new QueryFilterInterpreter([]),
            $searchBuilder,
            $listingLoader,
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

        $searchResult = new EntitySearchResult(
            'product',
            3,
            new ProductCollection([$withName, $withoutName, $withoutPrice]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $searchBuilder = $this->createMock(ProductSearchBuilderInterface::class);
        $searchBuilder->expects(self::once())->method('build');

        $listingLoader = $this->createMock(ProductListingLoader::class);
        $listingLoader->expects(self::once())->method('load')->willReturn($searchResult);

        $service = new JvProductSearchService(
            new QueryFilterInterpreter([]),
            $searchBuilder,
            $listingLoader,
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

        $searchResult = new EntitySearchResult(
            'product',
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $searchBuilder = $this->createMock(ProductSearchBuilderInterface::class);
        $searchBuilder->expects(self::once())->method('build');

        $listingLoader = $this->createMock(ProductListingLoader::class);
        $listingLoader->expects(self::once())->method('load')->willReturn($searchResult);

        $service = new JvProductSearchService(
            $interpreter,
            $searchBuilder,
            $listingLoader,
            new NullLogger(),
        );

        $result = $service->suggest('braunes sofa', 10, $this->salesChannelContext());

        self::assertSame('braunes sofa', $result->getRemainingSearchTerm());
        self::assertSame([], $result->getInterpretedFilters());
    }

    public function testInvalidExtraOptionIdsAreIgnored(): void
    {
        $searchResult = new EntitySearchResult(
            'product',
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $searchBuilder = $this->createMock(ProductSearchBuilderInterface::class);
        $listingLoader = $this->createMock(ProductListingLoader::class);
        $listingLoader->expects(self::once())->method('load')->willReturn($searchResult);

        $service = new JvProductSearchService(
            new QueryFilterInterpreter([]),
            $searchBuilder,
            $listingLoader,
            new NullLogger(),
        );

        /** @var list<mixed> $junk */
        $junk = ['not-a-uuid', ['nested'], Uuid::randomHex()];

        $result = $service->search(
            'sofa',
            1,
            24,
            $junk,
            $this->salesChannelContext(),
        );

        self::assertSame('sofa', $result->getQuery());
        self::assertSame(0, $result->getTotal());
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

        return $context;
    }
}
