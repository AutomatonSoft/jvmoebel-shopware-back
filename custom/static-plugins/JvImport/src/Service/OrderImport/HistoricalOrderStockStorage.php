<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

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

    public function __construct(private readonly AbstractStockStorage $decorated)
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

        $this->decorated->alter($changes, $context);
    }

    /** @param list<string> $productIds */
    public function index(array $productIds, Context $context): void
    {
        $this->decorated->index($productIds, $context);
    }
}
