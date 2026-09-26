<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Service\Query;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolMappedProduct;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPageMappingResult;
use Jv\Promotion\Service\Query\ListPromotionCollectionsService;
use PHPUnit\Framework\TestCase;

final class ListPromotionCollectionsServiceTest extends TestCase
{
    public function testItReturnsGroupedCollectionsWithTotal(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->willReturn(2);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => '175220799', 'name' => 'Sofa L6004B', 'product_count' => 12],
                ['id' => '175220800', 'name' => 'Sofa L6005A', 'product_count' => 8],
            ]);

        $service = new ListPromotionCollectionsService($connection);
        $result = $service->execute(498371, 'Sofa', 25, 0);

        self::assertSame(2, $result['total']);
        self::assertSame([
            ['id' => '175220799', 'name' => 'Sofa L6004B', 'productCount' => 12],
            ['id' => '175220800', 'name' => 'Sofa L6005A', 'productCount' => 8],
        ], $result['data']);
    }

    public function testItOmitsQueryFilterWhenSearchIsEmpty(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->willReturn(0);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::logicalAnd(
                    self::stringContains('factory_id = :factoryId'),
                    self::logicalNot(self::stringContains('collection_name LIKE')),
                ),
                self::callback(static function (array $parameters): bool {
                    return 498371 === $parameters['factoryId']
                        && 10 === $parameters['limit']
                        && 5 === $parameters['offset']
                        && !isset($parameters['query']);
                }),
            )
            ->willReturn([]);

        $service = new ListPromotionCollectionsService($connection);
        $result = $service->execute(498371, null, 10, 5);

        self::assertSame(['data' => [], 'total' => 0], $result);
    }

    public function testItUsesProductNameWhenLiveCollectionNameIsMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(0);
        $connection->method('fetchAllAssociative')->willReturn([]);
        $product = new AfterCoolMappedProduct(
            'JV',
            'lister',
            477277,
            '180023785',
            '180023785',
            '4260174423463',
            '180023785',
            'Sofa L6004B',
            1,
            10.0,
            1,
            null,
            [],
            [],
            stammartikelId: '175220799',
        );
        $catalog = new class($product) implements AfterCoolProductSourceInterface {
            public int $calls = 0;

            public function __construct(private readonly AfterCoolMappedProduct $product)
            {
            }

            public function getFactories(): array
            {
                return [];
            }

            public function getProductPage(int $factoryId, int $offset, int $limit = 100, ?string $query = null): AfterCoolProductPageMappingResult
            {
                ++$this->calls;

                return new AfterCoolProductPageMappingResult([$this->product], [], [], 1, $offset, true);
            }
        };

        $result = (new ListPromotionCollectionsService($connection, $catalog))->execute(477277, null, 50, 0);

        self::assertSame(1, $catalog->calls);
        self::assertSame('175220799', $result['data'][0]['id']);
        self::assertSame('Sofa L6004B', $result['data'][0]['name']);
        self::assertSame(1, $result['data'][0]['productCount']);
    }

    public function testItKeepsAfterCoolSearchOrder(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(0);
        $connection->method('fetchAllAssociative')->willReturn([]);
        $zebra = new AfterCoolMappedProduct('JV', 'lister', 498371, '2', '2', '4260174423463', '2', 'Zebra Sofa', 1, 10.0, 1, null, [], [], stammartikelId: '200');
        $alpha = new AfterCoolMappedProduct('JV', 'lister', 498371, '1', '1', '4260174423463', '1', 'Alpha Sofa', 2, 10.0, 1, null, [], [], stammartikelId: '100');
        $catalog = new class($zebra, $alpha) implements AfterCoolProductSourceInterface {
            public function __construct(
                private readonly AfterCoolMappedProduct $zebra,
                private readonly AfterCoolMappedProduct $alpha,
            ) {
            }

            public function getFactories(): array
            {
                return [];
            }

            public function getProductPage(int $factoryId, int $offset, int $limit = 100, ?string $query = null): AfterCoolProductPageMappingResult
            {
                TestCase::assertSame('Sofa', $query);

                return new AfterCoolProductPageMappingResult([$this->zebra, $this->alpha], [], [], 2, $offset, false);
            }
        };

        $result = (new ListPromotionCollectionsService($connection, $catalog))->execute(498371, 'Sofa', 50, 0);

        self::assertSame(['Zebra Sofa', 'Alpha Sofa'], array_column($result['data'], 'name'));
    }
}
