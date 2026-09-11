<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockDataCollection;
use Shopware\Core\Content\Product\Stock\StockLoadRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The core order stock subscriber moves stock both on ordinary order_line_item writes and on
 * `order.state` transitions (cancel/reopen read the order's current product lines directly by
 * SQL, bypassing any DAL write event). Historical imports are explicitly outside stock
 * accounting for their whole lifecycle, so both paths are intercepted here.
 */
final class HistoricalOrderStockStorage extends AbstractStockStorage implements EventSubscriberInterface
{
    public const CONTEXT_EXTENSION = 'jv_cosmoshop_historical_order_import';
    public const CAPTURED_EXTENSION = 'jv_cosmoshop_historical_line_items';

    /** @return array<string, string|array{0: string, 1: int}> */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'captureHistoricalLines',
            // Must run before the core OrderStockSubscriber (default priority 0) so the capture is in place before it calls alter().
            StateMachineTransitionEvent::class => ['captureHistoricalOrderTransition', 10],
        ];
    }

    public function __construct(private readonly AbstractStockStorage $decorated, private readonly Connection $connection)
    {
    }

    public function getDecorated(): AbstractStockStorage
    {
        return $this->decorated;
    }

    public function load(StockLoadRequest $stockRequest, SalesChannelContext $context): StockDataCollection
    {
        return $this->decorated->load($stockRequest, $context);
    }

    /** @param list<StockAlteration> $changes */
    public function alter(array $changes, Context $context): void
    {
        if (null !== $context->getExtension(self::CONTEXT_EXTENSION)) {
            return;
        }
        $captured = $context->getExtension(self::CAPTURED_EXTENSION);
        if ($captured instanceof ArrayStruct) {
            $historical = $captured->get('ids');
            if (is_array($historical)) {
                // The captured ids are lower-hex (kept JSON/UTF-8 safe for the Context extension,
                // which participates in sales-channel-context cache-key hashing elsewhere); the core
                // subscriber, however, builds StockAlteration::$lineItemId from a plain, un-hexed `id`
                // column select, so it arrives here as raw binary. Hex-encode it to compare.
                $changes = array_values(array_filter($changes, static fn (StockAlteration $change): bool => !in_array(bin2hex($change->lineItemId), $historical, true)));
            }
        }
        if ([] === $changes) {
            return;
        }
        $this->decorated->alter($changes, $context);
    }

    public function captureHistoricalLines(EntityWriteEvent $event): void
    {
        $ids = $event->getIds('order_line_item');
        if ([] === $ids) {
            return;
        }
        $commands = $event->getCommandsForEntity('order_line_item');
        $hasHistoricalPayload = false;
        $hasExistingWrite = false;
        foreach ($commands as $command) {
            $hasHistoricalPayload = $hasHistoricalPayload || self::payloadIsHistorical($command->getPayload());
            $hasExistingWrite = $hasExistingWrite || $command->getEntityExistence()->exists();
        }
        // New ordinary checkout line-items cannot be historical and must not incur marker SQL.
        if (!$hasHistoricalPayload && !$hasExistingWrite) {
            return;
        }
        $historical = $this->connection->fetchFirstColumn(
            "SELECT LOWER(HEX(id)) FROM order_line_item WHERE id IN (?) AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.jv_cosmoshop_historical_import')) = 'true'",
            [array_map(static fn (string $id): string => Uuid::fromHexToBytes($id), $ids)],
            [ArrayParameterType::BINARY],
        );
        // The context can be a long-lived, mutated-in-place instance (e.g. a StateMachineRegistry
        // transition scope reused across several transitions in the same request), so a stale
        // capture from an earlier write must not leak into this one.
        if ([] !== $historical) {
            $event->getContext()->addExtension(self::CAPTURED_EXTENSION, new ArrayStruct(['ids' => $historical]));
        } else {
            $event->getContext()->removeExtension(self::CAPTURED_EXTENSION);
        }
    }

    /**
     * Order state transitions (cancel/reopen) never go through an EntityWriteEvent for
     * order_line_item, so captureHistoricalLines never runs for them. One marker lookup per
     * transition tells us whether the order is historical and, if so, which of its own lines
     * to exempt from the alter() call the core subscriber is about to make.
     */
    public function captureHistoricalOrderTransition(StateMachineTransitionEvent $event): void
    {
        if ('order' !== $event->getEntityName()) {
            return;
        }
        // `order`.custom_fields is a registered custom-field-set JsonField: Shopware normalises the
        // registered bool value to a JSON number on write (`1`, not `true`), so JSON_UNQUOTE(...) is
        // compared against both textual forms instead of the JSON_UNQUOTE(...) = 'true' pattern used
        // for the untyped, plain JSON `payload` column in captureHistoricalLines above.
        $historicalLineIds = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT LOWER(HEX(order_line_item.id))
                FROM `order`
                INNER JOIN order_line_item ON order_line_item.order_id = `order`.id AND order_line_item.version_id = `order`.version_id
                WHERE `order`.id = :orderId
                    AND `order`.version_id = :versionId
                    AND JSON_UNQUOTE(JSON_EXTRACT(`order`.custom_fields, '$.jv_cosmoshop_historical_import')) IN ('1', 'true')
                SQL,
            [
                'orderId' => Uuid::fromHexToBytes($event->getEntityId()),
                'versionId' => Uuid::fromHexToBytes($event->getContext()->getVersionId()),
            ],
        );
        // The context can be a long-lived, mutated-in-place instance (StateMachineRegistry::transition()
        // reuses the caller's Context object across the whole call, scope() included), so a stale
        // capture from an earlier transition on the same order must not leak into this one.
        if ([] !== $historicalLineIds) {
            $event->getContext()->addExtension(self::CAPTURED_EXTENSION, new ArrayStruct(['ids' => $historicalLineIds]));
        } else {
            $event->getContext()->removeExtension(self::CAPTURED_EXTENSION);
        }
    }

    /** @param array<string, mixed> $payload */
    private static function payloadIsHistorical(array $payload): bool
    {
        $inner = $payload['customFields'] ?? $payload['custom_fields'] ?? $payload['payload'] ?? null;
        // The order_line_item `payload` JSON column may arrive already-serialized as a string in write commands.
        if (is_string($inner)) {
            try {
                $inner = json_decode($inner, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return false;
            }
        }

        return is_array($inner) && true === ($inner['jv_cosmoshop_historical_import'] ?? false);
    }

    /** @param list<string> $productIds */
    public function index(array $productIds, Context $context): void
    {
        $this->decorated->index($productIds, $context);
    }
}
