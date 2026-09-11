<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Struct\ArrayStruct;

/** Persists one projected chunk and performs historical-line reconciliation in one indexed query. */
final readonly class CosmoShopHistoricalOrderWriter
{
    /** @param EntityRepository<OrderCollection> $orders
     * @param EntityRepository<OrderLineItemCollection> $lineItems
     */
    public function __construct(private EntityRepository $orders, private EntityRepository $lineItems, private Connection $connection, private LoggerInterface $logger)
    {
    }

    /** @param list<array{payload: array<string, mixed>, orderNumber: string}> $writes
     * @param array<string, int> $counts
     *
     * @return list<string>
     */
    public function write(array $writes, Context $context, array &$counts): array
    {
        $context->addExtension(HistoricalOrderStockStorage::CONTEXT_EXTENSION, new ArrayStruct());
        try {
            try {
                $this->connection->transactional(function () use ($writes, $context): void {
                    $this->removeStale($writes, $context);
                    $this->orders->upsert(array_column($writes, 'payload'), $context);
                });
                $counts['written'] += count($writes);

                return array_column($writes, 'orderNumber');
            } catch (\Throwable $exception) {
                $this->logger->warning('Historical order import chunk write failed; retrying records individually.', [...$this->runContext($context), 'exceptionClass' => $exception::class]);
                $written = [];
                foreach ($writes as $write) {
                    try {
                        $this->connection->transactional(function () use ($write, $context): void {
                            $this->removeStale([$write], $context);
                            $this->orders->upsert([$write['payload']], $context);
                        });
                        ++$counts['written'];
                        $written[] = $write['orderNumber'];
                    } catch (\Throwable $recordException) {
                        $this->logger->error('Historical order import record write failed.', [...$this->runContext($context), 'exceptionClass' => $recordException::class]);
                        ++$counts['failed'];
                    }
                }

                return $written;
            }
        } finally {
            $context->removeExtension(HistoricalOrderStockStorage::CONTEXT_EXTENSION);
        }
    }

    /** @param list<array{payload: array<string,mixed>,orderNumber:string}> $writes */
    private function removeStale(array $writes, Context $context): void
    {
        $payloads = array_column($writes, 'payload');
        $ids = array_column($payloads, 'id');
        if ([] === $ids) {
            return;
        }
        $rows = $this->connection->fetchAllAssociative("SELECT HEX(id) AS id, HEX(order_id) AS order_id FROM order_line_item WHERE order_id IN (?) AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.jv_cosmoshop_historical_import')) = 'true'", [array_map(static fn (string $id): string => hex2bin($id), $ids)], [ArrayParameterType::BINARY]);
        $incoming = [];
        foreach ($payloads as $payload) {
            $incoming[$payload['id']] = array_column($payload['lineItems'] ?? [], 'id');
        }
        $stale = [];
        foreach ($rows as $row) {
            if (!in_array(strtolower((string) $row['id']), $incoming[strtolower((string) $row['order_id'])] ?? [], true)) {
                $stale[] = strtolower((string) $row['id']);
            }
        }
        if ([] !== $stale) {
            $this->lineItems->delete(array_map(static fn (string $id): array => ['id' => $id], $stale), $context);
        }
    }

    /** @return array<string, scalar> */
    private function runContext(Context $context): array
    {
        $extension = $context->getExtension('jv_cosmoshop_import_run');

        return $extension instanceof ArrayStruct ? $extension->all() : [];
    }
}
