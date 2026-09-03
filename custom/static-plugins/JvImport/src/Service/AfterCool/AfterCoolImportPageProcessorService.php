<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

use Doctrine\DBAL\Connection;
use Jv\Import\Core\Content\AfterCoolImportError\AfterCoolImportErrorCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunEntity;
use Jv\Import\Core\Content\AfterCoolProductSource\AfterCoolProductSourceCollection;
use Jv\Import\Service\AfterCool\Contract\AfterCoolImportPageProcessor;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolMappedProduct;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductIssue;
use Jv\Import\Service\AfterCool\Exception\AfterCoolProductWriteValidationException;
use Jv\Import\Service\AfterCool\Exception\AfterCoolUnexpectedPageOffsetException;
use Jv\Import\Service\ProductImport\ResolveDefaultProductTaxService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Lock\LockFactory;

/**
 * Owns one idempotent Aftercool page checkpoint. Upstream access and mapping
 * remain in Integration; this class only applies the import use case.
 */
final readonly class AfterCoolImportPageProcessorService implements AfterCoolImportPageProcessor
{
    /**
     * @param EntityRepository<AfterCoolImportRunCollection>     $runRepository
     * @param EntityRepository<AfterCoolProductSourceCollection> $sourceRepository
     * @param EntityRepository<AfterCoolImportErrorCollection>   $errorRepository
     */
    public function __construct(
        private AfterCoolProductSourceInterface $source,
        private AfterCoolProductPageResolverService $pageResolver,
        private BuildAfterCoolShopwareProductRecordService $recordBuilder,
        private AfterCoolSyncBatchWriter $writer,
        private AfterCoolMediaStageService $mediaStage,
        private AfterCoolStagedMediaProcessorService $stagedMediaProcessor,
        private ResolveDefaultProductTaxService $defaultTax,
        private EntityRepository $runRepository,
        private EntityRepository $sourceRepository,
        private EntityRepository $errorRepository,
        private Connection $connection,
        private LockFactory $lockFactory,
    ) {
    }

    public function process(string $runId, int $offset, Context $context): AfterCoolPageProcessingResult
    {
        $lock = $this->lockFactory->createLock('jv-aftercool-import-run-'.$runId, 300.0);
        if (!$lock->acquire(true)) {
            throw new \RuntimeException('Aftercool import run lock could not be acquired.');
        }
        try {
            return $this->processLocked($runId, $offset, $context);
        } finally {
            $lock->release();
        }
    }

    private function processLocked(string $runId, int $offset, Context $context): AfterCoolPageProcessingResult
    {
        $run = $this->loadRun($runId, $context);
        if (in_array($run->getStatus(), ['completed', 'completed_with_errors', 'failed'], true)) {
            return AfterCoolPageProcessingResult::completed();
        }
        if ($offset < $run->getNextOffset()) {
            return in_array($run->getStatus(), ['queued', 'running'], true)
                ? AfterCoolPageProcessingResult::continueWith($run->getNextOffset())
                : AfterCoolPageProcessingResult::completed();
        }
        if ($offset > $run->getNextOffset()) {
            throw new AfterCoolUnexpectedPageOffsetException();
        }

        $page = $this->source->getProductPage($run->getFactoryId(), $offset);
        $mapping = $page;
        $tax = $this->defaultTax->execute();
        $records = [];
        $products = [];
        $issues = $mapping->issues;

        foreach ($this->pageResolver->resolve($mapping->products, $context) as $resolvedProduct) {
            $product = $resolvedProduct->product;
            if (null !== $resolvedProduct->issue) {
                $issues[] = $resolvedProduct->issue;
                continue;
            }

            try {
                $payload = $this->recordBuilder->build(
                    $product,
                    $resolvedProduct->productId,
                    $tax->id,
                    $tax->rate,
                    Defaults::CURRENCY,
                    Market::Germany->languageId(),
                    $resolvedProduct->existingPrices,
                );
                $records[] = new AfterCoolProductWriteRecord(
                    $product->sourceProductId,
                    $payload,
                );
                $products[$product->sourceProductId] = [$product, $resolvedProduct, $payload['id']];
            } catch (AfterCoolProductWriteValidationException $exception) {
                $issues = array_values(array_filter(
                    $issues,
                    static fn (AfterCoolProductIssue $issue): bool => !($issue->productId === $product->sourceProductId && 'invalid_price' === $issue->code),
                ));
                $issues[] = new AfterCoolProductIssue(
                    $product->sourceProductId,
                    'failed',
                    $exception->safeCode(),
                    'Aftercool product cannot be created without a valid price.',
                    $product->sourceArtikelnummer,
                    $product->ean,
                    $product->rowNo,
                );
            }
        }

        $this->connection->transactional(function () use ($run, $page, $offset, $records, $products, &$issues, $context): void {
            $writeResult = $this->writer->write($records, $context);
            foreach ($writeResult->failures as $failure) {
                [$product] = $products[$failure->sourceProductId] ?? [null];
                $issues[] = new AfterCoolProductIssue($failure->sourceProductId, 'failed', $failure->code, $failure->message, $product?->sourceArtikelnummer, $product?->ean, $product?->rowNo);
            }
            $successful = array_fill_keys($writeResult->successfulSourceProductIds, true);
            $created = 0;
            $updated = 0;
            foreach ($products as [$product, $resolvedProduct, $productId]) {
                if (!isset($successful[$product->sourceProductId])) {
                    continue;
                }
                $this->upsertSourceLink($product, $resolvedProduct->sourceLinkId, $productId, $context);
                $this->mediaStage->stage($run->getId(), $offset, $product->sourceProductId, $productId, $product->mediaUrls, $resolvedProduct->hasCover);
                if ($resolvedProduct->isNew()) {
                    ++$created;
                } else {
                    ++$updated;
                }
            }

            $skipped = 0;
            $failed = 0;
            foreach ($issues as $issue) {
                if ($issue->countsAsRecord) {
                    'skipped' === $issue->result ? ++$skipped : ++$failed;
                }
                $this->recordIssue($run, $offset, $issue, $context);
            }

            $progress = AfterCoolImportProgress::fromPersisted(
                $run->getStatus(),
                $run->getTotal(),
                $run->getNextOffset(),
                $run->getProcessed(),
                $run->getCreated(),
                $run->getUpdated(),
                $run->getSkipped(),
                $run->getFailed(),
            )->checkpoint(new AfterCoolPageOutcome($offset, $page->total, $created, $updated, $skipped, $failed, $page->hasMore));
            if ($progress->totalChanged) {
                $this->recordIssue($run, $offset, new AfterCoolProductIssue(null, 'failed', 'aftercool_total_changed', 'Aftercool page total changed during import.', countsAsRecord: false), $context);
            }
            if (!$page->hasMore && $this->hasReportedErrors($run->getId(), $context)) {
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
            if (!$page->hasMore) {
                $payload['finishedAt'] = new \DateTimeImmutable();
                $payload['activeFactoryKey'] = null;
            }
            $this->runRepository->update([$payload], $context);
        });

        $this->stagedMediaProcessor->process($runId, $offset, $context);

        return $page->hasMore ? AfterCoolPageProcessingResult::continueWith($offset + 100) : AfterCoolPageProcessingResult::completed();
    }

    private function loadRun(string $runId, Context $context): AfterCoolImportRunEntity
    {
        $run = $this->runRepository->search(new Criteria([$runId]), $context)->first();
        if (!$run instanceof AfterCoolImportRunEntity) {
            throw new \InvalidArgumentException('Aftercool import run does not exist.');
        }

        return $run;
    }

    private function upsertSourceLink(AfterCoolMappedProduct $product, string $sourceId, string $productId, Context $context): void
    {
        $this->sourceRepository->upsert([[
            'id' => $sourceId,
            'account' => $product->account,
            'dataset' => $product->dataset,
            'factoryId' => $product->factoryId,
            'sourceProductId' => $product->sourceProductId,
            'productId' => $productId,
            'productVersionId' => Defaults::LIVE_VERSION,
            'sourceArtikelnummer' => $product->sourceArtikelnummer,
            'sourceEan' => $product->ean,
            'lastSeenAt' => new \DateTimeImmutable(),
        ]], $context);
    }

    private function recordIssue(AfterCoolImportRunEntity $run, int $offset, AfterCoolProductIssue $issue, Context $context): void
    {
        $this->errorRepository->create([[
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
            'createdAt' => new \DateTimeImmutable(),
        ]], $context);
    }

    private function hasReportedErrors(string $runId, Context $context): bool
    {
        $criteria = (new Criteria())->setLimit(1);
        $criteria->addFilter(new EqualsFilter('runId', $runId));
        $criteria->addFilter(new EqualsFilter('result', 'failed'));

        return $this->errorRepository->searchIds($criteria, $context)->getTotal() > 0;
    }
}
