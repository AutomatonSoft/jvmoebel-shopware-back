<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Service\Query;

use Doctrine\DBAL\Connection;
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

    public function testItReturnsEmptyResultWhenTheIndexHasNoCollections(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(0);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $result = (new ListPromotionCollectionsService($connection))->execute(477277, null, 50, 0);

        self::assertSame(['data' => [], 'total' => 0], $result);
    }
}
