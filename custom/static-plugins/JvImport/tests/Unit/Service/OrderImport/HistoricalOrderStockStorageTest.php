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
        $connection->expects(self::once())->method('fetchFirstColumn')->willReturn(['historical-line']);
        $decorated->expects(self::once())->method('alter')->with(
            self::callback(static fn (array $changes): bool => 1 === count($changes) && 'normal-line' === $changes[0]->lineItemId),
            self::isInstanceOf(Context::class),
        );

        $storage = new HistoricalOrderStockStorage($decorated, $connection);
        $storage->alter([
            new StockAlteration('historical-line', 'product-a', 2, 0),
            new StockAlteration('normal-line', 'product-b', 2, 0),
        ], Context::createDefaultContext());
    }

    public function testOnlyHistoricalBatchIsDropped(): void
    {
        $decorated = $this->createMock(AbstractStockStorage::class);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['historical-line']);
        $decorated->expects(self::never())->method('alter');

        $storage = new HistoricalOrderStockStorage($decorated, $connection);
        $storage->alter([new StockAlteration('historical-line', 'product-a', 2, 0)], Context::createDefaultContext());
    }
}
