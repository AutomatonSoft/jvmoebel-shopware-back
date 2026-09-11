<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Jv\Import\Service\OrderImport\Dto\HistoricalOrderWriteResult;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Struct\ArrayStruct;

/** Persists one projected chunk and performs historical-line reconciliation in one indexed query per chunk. */
final readonly class CosmoShopHistoricalOrderWriter
{
    /**
     * @param EntityRepository<OrderCollection>         $orderRepository
     * @param EntityRepository<OrderLineItemCollection> $orderLineItemRepository
     */
    public function __construct(
        private EntityRepository $orderRepository,
        private EntityRepository $orderLineItemRepository,
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    /** @param list<array<string, mixed>> $payloads */
    public function write(array $payloads, Context $context): HistoricalOrderWriteResult
    {
        if ([] === $payloads) {
            return new HistoricalOrderWriteResult(0, 0, []);
        }

        $context->addExtension(HistoricalOrderStockStorage::CONTEXT_EXTENSION, new ArrayStruct());
        try {
            return $this->writeChunk($payloads, $context);
        } finally {
            $context->removeExtension(HistoricalOrderStockStorage::CONTEXT_EXTENSION);
        }
    }

    /** @param list<array<string, mixed>> $payloads */
    private function writeChunk(array $payloads, Context $context): HistoricalOrderWriteResult
    {
        try {
            $this->connection->transactional(function () use ($payloads, $context): void {
                $this->removeStale($payloads, $context);
                $this->orderRepository->upsert($payloads, $context);
            });

            /** @var list<string> $orderNumbers */
            $orderNumbers = array_column($payloads, 'orderNumber');

            return new HistoricalOrderWriteResult(count($payloads), 0, $orderNumbers);
        } catch (\Throwable $exception) {
            $this->logger->warning('Historical order import chunk write failed; retrying records individually.', [...$this->runContext($context), 'exceptionClass' => $exception::class]);

            return $this->writeIndividually($payloads, $context);
        }
    }

    /** @param list<array<string, mixed>> $payloads */
    private function writeIndividually(array $payloads, Context $context): HistoricalOrderWriteResult
    {
        $written = 0;
        $failed = 0;
        $writtenOrderNumbers = [];
        foreach ($payloads as $payload) {
            try {
                $this->connection->transactional(function () use ($payload, $context): void {
                    $this->removeStale([$payload], $context);
                    $this->orderRepository->upsert([$payload], $context);
                });
                ++$written;
                $writtenOrderNumbers[] = (string) $payload['orderNumber'];
            } catch (\Throwable $recordException) {
                $this->logger->error('Historical order import record write failed.', [...$this->runContext($context), 'exceptionClass' => $recordException::class]);
                ++$failed;
            }
        }

        return new HistoricalOrderWriteResult($written, $failed, $writtenOrderNumbers);
    }

    /** @param list<array<string, mixed>> $payloads */
    private function removeStale(array $payloads, Context $context): void
    {
        $ids = array_column($payloads, 'id');
        if ([] === $ids) {
            return;
        }
        // Batched, indexed lookup: one query per chunk instead of one per order.
        $rows = $this->connection->fetchAllAssociative(
            "SELECT HEX(id) AS id, HEX(order_id) AS order_id FROM order_line_item WHERE order_id IN (?) AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.jv_cosmoshop_historical_import')) = 'true'",
            [array_map(static fn (string $id): string => hex2bin($id), $ids)],
            [ArrayParameterType::BINARY],
        );
        $incoming = [];
        foreach ($payloads as $payload) {
            /** @var list<array<string, mixed>> $lineItems */
            $lineItems = $payload['lineItems'] ?? [];
            $incoming[(string) $payload['id']] = array_column($lineItems, 'id');
        }
        $stale = [];
        foreach ($rows as $row) {
            $orderId = strtolower((string) $row['order_id']);
            $lineId = strtolower((string) $row['id']);
            if (!in_array($lineId, $incoming[$orderId] ?? [], true)) {
                $stale[] = $lineId;
            }
        }
        if ([] !== $stale) {
            $this->orderLineItemRepository->delete(array_map(static fn (string $id): array => ['id' => $id], $stale), $context);
        }
    }

    /** @return array<string, scalar> */
    private function runContext(Context $context): array
    {
        $extension = $context->getExtension('jv_cosmoshop_import_run');

        return $extension instanceof ArrayStruct ? $extension->all() : [];
    }
}
