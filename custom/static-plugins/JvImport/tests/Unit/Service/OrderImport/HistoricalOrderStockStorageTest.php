<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\OrderImport;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\OrderImport\HistoricalOrderStockStorage;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;

final class HistoricalOrderStockStorageTest extends TestCase
{
    public function testHistoricalLineIsSuppressedAndNormalLineIsForwarded(): void
    {
        $decorated = $this->createMock(AbstractStockStorage::class);
        $connection = $this->createMock(Connection::class);
        $historicalId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $normalId = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        // The core stock subscriber builds StockAlteration::$lineItemId from a plain, un-hexed
        // `id` column select, so it always arrives as raw binary, never as a hex string.
        $decorated->expects(self::once())->method('alter')->with(
            self::callback(static fn (array $changes): bool => 1 === count($changes) && hex2bin($normalId) === $changes[0]->lineItemId),
            self::isInstanceOf(Context::class),
        );

        $context = Context::createDefaultContext();
        $connection->expects(self::once())->method('fetchFirstColumn')->willReturn([$historicalId]);
        $storage = new HistoricalOrderStockStorage($decorated, $connection);
        $storage->captureHistoricalLines($this->historicalWriteEvent($context, $historicalId));
        $storage->alter([
            new StockAlteration((string) hex2bin($historicalId), 'product-a', 2, 0),
            new StockAlteration((string) hex2bin($normalId), 'product-b', 2, 0),
        ], $context);
    }

    public function testOnlyHistoricalBatchIsDropped(): void
    {
        $decorated = $this->createMock(AbstractStockStorage::class);
        $connection = $this->createMock(Connection::class);
        $historicalId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $decorated->expects(self::never())->method('alter');

        $context = Context::createDefaultContext();
        $connection->expects(self::once())->method('fetchFirstColumn')->willReturn([$historicalId]);
        $storage = new HistoricalOrderStockStorage($decorated, $connection);
        $storage->captureHistoricalLines($this->historicalWriteEvent($context, $historicalId));
        $storage->alter([new StockAlteration((string) hex2bin($historicalId), 'product-a', 2, 0)], $context);
    }

    public function testOrdinaryCheckoutCreateDoesNotRunMarkerQuery(): void
    {
        $decorated = $this->createMock(AbstractStockStorage::class);
        $connection = $this->createMock(Connection::class);
        $normalId = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $decorated->expects(self::once())->method('alter');
        $context = Context::createDefaultContext();
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(false);
        $command = $this->createMock(WriteCommand::class);
        $command->method('getEntityName')->willReturn('order_line_item');
        $command->method('getDecodedPrimaryKey')->willReturn(['id' => $normalId]);
        $command->method('getPayload')->willReturn(['payload' => []]);
        $command->method('getEntityExistence')->willReturn($existence);
        $event = EntityWriteEvent::create(WriteContext::createFromContext($context), [$command]);

        $storage = new HistoricalOrderStockStorage($decorated, $connection);
        $storage->captureHistoricalLines($event);
        $storage->alter([new StockAlteration((string) hex2bin($normalId), 'product-b', 2, 0)], $context);
    }

    private function historicalWriteEvent(Context $context, string $id): EntityWriteEvent
    {
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(false);
        $command = $this->createMock(WriteCommand::class);
        $command->method('getEntityName')->willReturn('order_line_item');
        $command->method('getDecodedPrimaryKey')->willReturn(['id' => $id]);
        $command->method('getPayload')->willReturn(['payload' => ['jv_cosmoshop_historical_import' => true]]);
        $command->method('getEntityExistence')->willReturn($existence);

        return EntityWriteEvent::create(WriteContext::createFromContext($context), [$command]);
    }
}
