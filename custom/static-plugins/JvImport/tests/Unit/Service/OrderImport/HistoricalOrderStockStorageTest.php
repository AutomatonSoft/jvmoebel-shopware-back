<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\OrderImport;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\OrderImport\HistoricalOrderStockStorage;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Framework\Context;

final class HistoricalOrderStockStorageTest extends TestCase
{
    public function testHistoricalLineIsSuppressedAndNormalLineIsForwarded(): void
    {
        $decorated = $this->createMock(AbstractStockStorage::class);
        $connection = $this->createMock(Connection::class);
        $historicalId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $normalId = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $connection->expects(self::once())->method('fetchFirstColumn')->willReturn([$historicalId]);
        $decorated->expects(self::once())->method('alter')->with(
            self::callback(static fn (array $changes): bool => 1 === count($changes) && $normalId === $changes[0]->lineItemId),
            self::isInstanceOf(Context::class),
        );

        $storage = new HistoricalOrderStockStorage($decorated, $connection);
        $storage->alter([
            new StockAlteration($historicalId, 'product-a', 2, 0),
            new StockAlteration($normalId, 'product-b', 2, 0),
        ], Context::createDefaultContext());
    }

    public function testOnlyHistoricalBatchIsDropped(): void
    {
        $decorated = $this->createMock(AbstractStockStorage::class);
        $connection = $this->createMock(Connection::class);
        $historicalId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $connection->method('fetchFirstColumn')->willReturn([$historicalId]);
        $decorated->expects(self::never())->method('alter');

        $storage = new HistoricalOrderStockStorage($decorated, $connection);
        $storage->alter([new StockAlteration($historicalId, 'product-a', 2, 0)], Context::createDefaultContext());
    }
}
