<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockDataCollection;
use Shopware\Core\Content\Product\Stock\StockLoadRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The core order stock subscriber works on every DAL product line write. Historical
 * imports are explicitly outside stock accounting, so their context opts out here.
 */
final class HistoricalOrderStockStorage extends AbstractStockStorage
{
    public const CONTEXT_EXTENSION = 'jv_cosmoshop_historical_order_import';

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

        $ids = array_values(array_unique(array_map(static fn (StockAlteration $change): string => $change->lineItemId, $changes)));
        if ([] !== $ids) {
            $historical = $this->connection->fetchFirstColumn(
                "SELECT LOWER(HEX(id)) FROM order_line_item WHERE LOWER(HEX(id)) IN (?) AND JSON_EXTRACT(payload, '$.jv_cosmoshop_historical_import') = true",
                [$ids],
                [ArrayParameterType::STRING],
            );
            if ([] !== $historical) {
                $changes = array_values(array_filter($changes, static fn (StockAlteration $change): bool => !in_array(strtolower($change->lineItemId), $historical, true)));
            }
        }
        if ([] === $changes) {
            return;
        }

        $this->decorated->alter($changes, $context);
    }

    /** @param list<string> $productIds */
    public function index(array $productIds, Context $context): void
    {
        $this->decorated->index($productIds, $context);
    }
}
