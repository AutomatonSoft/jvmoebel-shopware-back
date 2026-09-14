<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\OrderImport;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\OrderImport\CosmoShopHistoricalOrderWriter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopHistoricalOrderWriterTest extends TestCase
{
    public function testAChunkIsWrittenWithOneBatchedBinaryStaleLineLookup(): void
    {
        /** @var list<array{sql: string, params: array<mixed>}> $queries */
        $queries = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback): mixed => $callback($connection));
        foreach (['fetchAllAssociative', 'fetchFirstColumn', 'fetchAllKeyValue', 'fetchAllNumeric', 'fetchAllAssociativeIndexed'] as $method) {
            $connection->method($method)->willReturnCallback(static function (string $sql, array $params = []) use (&$queries): array {
                $queries[] = ['sql' => $sql, 'params' => $params];

                return [];
            });
        }
        foreach (['fetchOne', 'fetchAssociative', 'executeQuery', 'executeStatement'] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $orders = $this->createMock(EntityRepository::class);
        $orders->expects(self::once())->method('upsert')->with(self::callback(static fn (array $payloads): bool => 3 === count($payloads)));
        $lineItems = $this->createMock(EntityRepository::class);
        $lineItems->expects(self::never())->method('delete');

        $writer = new CosmoShopHistoricalOrderWriter(
            orderRepository: $orders,
            orderLineItemRepository: $lineItems,
            connection: $connection,
            logger: new NullLogger(),
        );
        $result = $writer->write([$this->payload('1'), $this->payload('2'), $this->payload('3')], Context::createDefaultContext());

        self::assertSame(3, $result->written);
        self::assertSame(0, $result->failed);
        self::assertSame(['1', '2', '3'], $result->writtenOrderNumbers);
        self::assertCount(1, $queries, 'Stale-line reconciliation must be one query per chunk, not one per order.');
        self::assertStringContainsString('order_id IN', $queries[0]['sql']);
        self::assertStringNotContainsString('HEX(order_id) =', $queries[0]['sql']);
        self::assertStringNotContainsString('LOWER(HEX(order_id))', $queries[0]['sql']);
        $idLists = array_values(array_filter($queries[0]['params'], 'is_array'));
        self::assertCount(1, $idLists);
        self::assertCount(3, $idLists[0]);
        foreach ($idLists[0] as $id) {
            self::assertIsString($id);
            self::assertSame(16, strlen($id), 'Order IDs must be bound as binary values to use the order_id index.');
        }
    }

    /** @return array<string, mixed> */
    private function payload(string $orderNumber): array
    {
        return ['id' => Uuid::randomHex(), 'orderNumber' => $orderNumber, 'lineItems' => [['id' => Uuid::randomHex()]]];
    }
}
