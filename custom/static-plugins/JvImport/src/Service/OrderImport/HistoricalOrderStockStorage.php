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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The core order stock subscriber works on every DAL product line write. Historical
 * imports are explicitly outside stock accounting, so their context opts out here.
 */
final class HistoricalOrderStockStorage extends AbstractStockStorage implements EventSubscriberInterface
{
    public const CONTEXT_EXTENSION = 'jv_cosmoshop_historical_order_import';
    public const CAPTURED_EXTENSION = 'jv_cosmoshop_historical_line_items';

    public static function getSubscribedEvents(): array
    {
        return [EntityWriteEvent::class => 'captureHistoricalLines'];
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
                $changes = array_values(array_filter($changes, static fn (StockAlteration $change): bool => !in_array(strtolower($change->lineItemId), array_map('strtolower', $historical), true)));
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
            $payload = $command->getPayload();
            $hasHistoricalPayload = $hasHistoricalPayload || self::payloadIsHistorical($payload);
            $hasExistingWrite = $hasExistingWrite || $command->getEntityExistence()->exists();
        }
        // New ordinary checkout line-items cannot be historical and must not incur marker SQL.
        if (!$hasHistoricalPayload && !$hasExistingWrite) {
            return;
        }
        $historical = $this->connection->fetchFirstColumn("SELECT LOWER(HEX(id)) FROM order_line_item WHERE id IN (?) AND JSON_EXTRACT(payload, '$.jv_cosmoshop_historical_import') = true", [array_map(static fn (string $id): string => Uuid::fromHexToBytes($id), $ids)], [ArrayParameterType::BINARY]);
        if ([] !== $historical) {
            $event->getContext()->addExtension(self::CAPTURED_EXTENSION, new ArrayStruct(['ids' => $historical]));
        }
    }

    /** @param array<string, mixed> $payload */
    private static function payloadIsHistorical(array $payload): bool
    {
        $customFields = $payload['customFields'] ?? $payload['custom_fields'] ?? $payload['payload'] ?? null;

        return is_array($customFields) && true === ($customFields['jv_cosmoshop_historical_import'] ?? false);
    }

    /** @param list<string> $productIds */
    public function index(array $productIds, Context $context): void
    {
        $this->decorated->index($productIds, $context);
    }
}
