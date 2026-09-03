<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Persistence;

use Doctrine\DBAL\Connection;
use Jv\Import\Core\Content\AfterCoolImportError\AfterCoolImportErrorCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunEntity;
use Jv\Import\Core\Content\AfterCoolProductSource\AfterCoolProductSourceCollection;
use Jv\Import\Service\AfterCool\Dto\AfterCoolPageOutcome;
use Jv\Import\Service\AfterCool\Dto\AfterCoolPreparedProduct;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductIssue;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductWriteRecord;
use Jv\Import\Service\AfterCool\Dto\AfterCoolSyncWriteFailure;
use Jv\Import\Service\AfterCool\Import\AfterCoolImportProgress;
use Jv\Import\Service\AfterCool\Write\AfterCoolShopwareProductWriter;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class AfterCoolPageCheckpointService
{
    /**
     * @param EntityRepository<AfterCoolImportRunCollection>     $runRepository
     * @param EntityRepository<AfterCoolProductSourceCollection> $sourceRepository
     * @param EntityRepository<AfterCoolImportErrorCollection>   $errorRepository
     */
    public function __construct(
        private AfterCoolShopwareProductWriter $writer,
        private AfterCoolMediaStageStore $mediaStage,
        private EntityRepository $runRepository,
        private EntityRepository $sourceRepository,
        private EntityRepository $errorRepository,
        private Connection $connection,
    ) {
    }

    /**
     * @param list<AfterCoolProductWriteRecord>       $records
     * @param array<string, AfterCoolPreparedProduct> $products
     * @param list<AfterCoolProductIssue>             $issues
     */
    public function checkpoint(
        AfterCoolImportRunEntity $run,
        int $offset,
        int $total,
        bool $hasMore,
        array $records,
        array $products,
        array $issues,
        Context $context,
    ): void {
        $this->connection->transactional(function () use ($run, $offset, $total, $hasMore, $records, $products, $issues, $context): void {
            $writeResult = $this->writer->write($records, $context);
            $issues = $this->appendWriteFailures($issues, $products, $writeResult->failures);
            $successful = array_fill_keys($writeResult->successfulSourceProductIds, true);
            $successfulProducts = array_filter(
                $products,
                static fn (AfterCoolPreparedProduct $prepared): bool => isset($successful[$prepared->product->sourceProductId]),
            );

            $this->upsertSourceLinks($successfulProducts, $context);
            $this->stageMedia($run, $offset, $successfulProducts);
            $this->recordIssues($run, $offset, $issues, $context);

            $outcome = $this->outcome($offset, $total, $hasMore, $successfulProducts, $issues);
            $progress = AfterCoolImportProgress::fromPersisted(
                $run->getStatus(),
                $run->getTotal(),
                $run->getNextOffset(),
                $run->getProcessed(),
                $run->getCreated(),
                $run->getUpdated(),
                $run->getSkipped(),
                $run->getFailed(),
            )->checkpoint($outcome);
            if ($progress->totalChanged) {
                $this->recordIssues($run, $offset, [new AfterCoolProductIssue(
                    null,
                    'failed',
                    'aftercool_total_changed',
                    'Aftercool page total changed during import.',
                    countsAsRecord: false,
                )], $context);
            }
            if (!$hasMore && $this->hasReportedErrors($run->getId(), $context)) {
                $progress = $progress->withTerminalErrors();
            }

            $payload = [
                'id' => $run->getId(),
                'status' => $progress->status,
                'total' => $progress->total,
                'nextOffset' => $progress->nextOffset,
                'processed' => $progress->processed,
                'created' => $progress->created,
                'updated' => $progress->updated,
                'skipped' => $progress->skipped,
                'failed' => $progress->failed,
            ];
            if (!$hasMore) {
                $payload['finishedAt'] = new \DateTimeImmutable();
                $payload['activeFactoryKey'] = null;
            }
            $this->runRepository->update([$payload], $context);
        });
    }

    /**
     * @param list<AfterCoolProductIssue>             $issues
     * @param array<string, AfterCoolPreparedProduct> $products
     * @param list<AfterCoolSyncWriteFailure>         $failures
     *
     * @return list<AfterCoolProductIssue>
     */
    private function appendWriteFailures(array $issues, array $products, array $failures): array
    {
        foreach ($failures as $failure) {
            $product = $products[$failure->sourceProductId]->product ?? null;
            $issues[] = new AfterCoolProductIssue(
                $failure->sourceProductId,
                'failed',
                $failure->code,
                $failure->message,
                $product?->sourceArtikelnummer,
                $product?->ean,
                $product?->rowNo,
            );
        }

        return $issues;
    }

    /** @param array<string, AfterCoolPreparedProduct> $products */
    private function upsertSourceLinks(array $products, Context $context): void
    {
        if ([] === $products) {
            return;
        }
        $now = new \DateTimeImmutable();
        $this->sourceRepository->upsert(array_values(array_map(
            static fn (AfterCoolPreparedProduct $prepared): array => [
                'id' => $prepared->resolved->sourceLinkId,
                'account' => $prepared->product->account,
                'dataset' => $prepared->product->dataset,
                'factoryId' => $prepared->product->factoryId,
                'sourceProductId' => $prepared->product->sourceProductId,
                'productId' => $prepared->productId,
                'productVersionId' => Defaults::LIVE_VERSION,
                'sourceArtikelnummer' => $prepared->product->sourceArtikelnummer,
                'sourceEan' => $prepared->product->ean,
                'lastSeenAt' => $now,
            ],
            $products,
        )), $context);
    }

    /** @param array<string, AfterCoolPreparedProduct> $products */
    private function stageMedia(AfterCoolImportRunEntity $run, int $offset, array $products): void
    {
        foreach ($products as $prepared) {
            $this->mediaStage->stage(
                $run->getId(),
                $offset,
                $prepared->product->sourceProductId,
                $prepared->productId,
                $prepared->product->mediaUrls,
                $prepared->resolved->hasCover,
            );
        }
    }

    /** @param list<AfterCoolProductIssue> $issues */
    private function recordIssues(AfterCoolImportRunEntity $run, int $offset, array $issues, Context $context): void
    {
        if ([] === $issues) {
            return;
        }
        $now = new \DateTimeImmutable();
        $this->errorRepository->create(array_map(
            static fn (AfterCoolProductIssue $issue): array => [
                'id' => Uuid::randomHex(),
                'runId' => $run->getId(),
                'factoryId' => $run->getFactoryId(),
                'productId' => $issue->productId,
                'artikelnummer' => $issue->artikelnummer,
                'ean' => $issue->ean,
                'offset' => $offset,
                'rowNo' => $issue->rowNo,
                'result' => $issue->result,
                'code' => $issue->code,
                'message' => $issue->message,
                'createdAt' => $now,
            ],
            $issues,
        ), $context);
    }

    /**
     * @param array<string, AfterCoolPreparedProduct> $products
     * @param list<AfterCoolProductIssue>             $issues
     */
    private function outcome(int $offset, int $total, bool $hasMore, array $products, array $issues): AfterCoolPageOutcome
    {
        $created = count(array_filter($products, static fn (AfterCoolPreparedProduct $prepared): bool => $prepared->resolved->isNew()));
        $updated = count($products) - $created;
        $skipped = 0;
        $failed = 0;
        foreach ($issues as $issue) {
            if (!$issue->countsAsRecord) {
                continue;
            }
            'skipped' === $issue->result ? ++$skipped : ++$failed;
        }

        return new AfterCoolPageOutcome($offset, $total, $created, $updated, $skipped, $failed, $hasMore);
    }

    private function hasReportedErrors(string $runId, Context $context): bool
    {
        $criteria = (new Criteria())->setLimit(1);
        $criteria->addFilter(new EqualsFilter('runId', $runId));
        $criteria->addFilter(new EqualsFilter('result', 'failed'));

        return $this->errorRepository->searchIds($criteria, $context)->getTotal() > 0;
    }
}
