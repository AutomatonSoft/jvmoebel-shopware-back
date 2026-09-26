<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Service\Query;

use Doctrine\DBAL\Connection;
use Jv\Promotion\Service\Query\ListPromotionFactoriesService;
use PHPUnit\Framework\TestCase;

final class ListPromotionFactoriesServiceTest extends TestCase
{
    public function testItReturnsLocalFactoriesWithProductCounts(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::stringContains('FROM jv_factory f'),
                self::callback(static fn (array $parameters): bool => 'aftercool:JV:lister' === $parameters['namespace']),
            )
            ->willReturn([
                ['id' => '498371', 'name' => 'UK-GANASI', 'product_count' => 2796],
                ['id' => '498681', 'name' => 'UK-Skorpion', 'product_count' => 0],
            ]);

        $result = (new ListPromotionFactoriesService($connection))->execute(null, 50);

        self::assertSame([
            ['id' => 498371, 'name' => 'UK-GANASI', 'productCount' => 2796],
            ['id' => 498681, 'name' => 'UK-Skorpion', 'productCount' => 0],
        ], $result);
    }

    public function testItFiltersFactoriesByQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => '498371', 'name' => 'UK-GANASI', 'product_count' => 100],
            ['id' => '498681', 'name' => 'UK-Skorpion', 'product_count' => 50],
        ]);

        $result = (new ListPromotionFactoriesService($connection))->execute('skorp', 50);

        self::assertSame([
            ['id' => 498681, 'name' => 'UK-Skorpion', 'productCount' => 50],
        ], $result);
    }
}
