<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;

final readonly class AfterCoolSyncBatchWriter
{
    public function __construct(private SyncServiceInterface $sync)
    {
    }

    /** @param list<AfterCoolProductWriteRecord> $records */
    public function write(array $records, Context $context): AfterCoolSyncWriteResult
    {
        if (100 < count($records)) {
            throw new \InvalidArgumentException('AfterCool batch must contain at most 100 records.');
        }
        [$success, $failures] = $this->writeRecords($records, $context);

        return new AfterCoolSyncWriteResult($success, $failures);
    }

    /** @param list<AfterCoolProductWriteRecord> $records
     * @return array{list<string>, list<AfterCoolSyncWriteFailure>} */
    private function writeRecords(array $records, Context $context): array
    {
        if ([] === $records) {
            return [[], []];
        }
        try {
            $this->sync->sync([new SyncOperation('aftercool-products', ProductDefinition::ENTITY_NAME, SyncOperation::ACTION_UPSERT, array_column($records, 'payload'))], $context, new SyncBehavior());

            return [array_column($records, 'sourceProductId'), []];
        } catch (WriteException) {
            if (1 === count($records)) {
                return [[], [new AfterCoolSyncWriteFailure($records[0]->sourceProductId, 'shopware_write_error', 'Shopware rejected this product record.')]];
            }
            $middle = intdiv(count($records), 2);
            [$leftSuccess, $leftFailures] = $this->writeRecords(array_slice($records, 0, $middle), $context);
            [$rightSuccess, $rightFailures] = $this->writeRecords(array_slice($records, $middle), $context);

            return [[...$leftSuccess, ...$rightSuccess], [...$leftFailures, ...$rightFailures]];
        }
    }
}
