<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Persistence;

use Doctrine\DBAL\Connection;
use Jv\Import\Core\Content\AfterCoolImportError\AfterCoolImportErrorCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunEntity;
use Jv\Import\Core\Content\AfterCoolImportRunProduct\AfterCoolImportRunProductCollection;
use Jv\Import\Core\Content\AfterCoolProductSource\AfterCoolProductSourceCollection;
use Jv\Import\Core\Content\Factory\FactoryCollection;
use Jv\Import\Core\Content\FactorySource\FactorySourceCollection;
use Jv\Import\Service\AfterCool\Dto\AfterCoolPageOutcome;
use Jv\Import\Service\AfterCool\Dto\AfterCoolPreparedProduct;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductIssue;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductWriteRecord;
use Jv\Import\Service\AfterCool\Dto\AfterCoolSyncWriteFailure;
use Jv\Import\Service\AfterCool\Import\AfterCoolImportProgress;
use Jv\Import\Service\AfterCool\Write\AfterCoolShopwareProductWriter;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class AfterCoolPageCheckpointService
{
    /**
     * @param EntityRepository<AfterCoolImportRunCollection>        $runRepository
     * @param EntityRepository<AfterCoolProductSourceCollection>    $sourceRepository
     * @param EntityRepository<AfterCoolImportRunProductCollection> $runProductRepository
     * @param EntityRepository<AfterCoolImportErrorCollection>      $errorRepository
     * @param EntityRepository<FactoryCollection>                   $factoryRepository
     * @param EntityRepository<FactorySourceCollection>             $factorySourceRepository
     * @param EntityRepository<ProductCollection>                   $productRepository
     */
    public function __construct(
        private AfterCoolShopwareProductWriter $writer,
        private AfterCoolMediaStageStore $mediaStage,
        private EntityRepository $runRepository,
        private EntityRepository $sourceRepository,
        private EntityRepository $runProductRepository,
        private EntityRepository $errorRepository,
        private Connection $connection,
        private EntityRepository $factoryRepository,
        private EntityRepository $factorySourceRepository,
        private EntityRepository $productRepository,
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
            $this->lockProductsForFactoryCheck($products);
            $conflictingIds = $this->conflictingFactoryProductIds($run, $products, $context);
            foreach ($conflictingIds as $sourceProductId) {
                $product = $products[$sourceProductId]->product;
                $issues[] = new AfterCoolProductIssue($sourceProductId, 'skipped', 'product_factory_conflict', 'Product is already linked to a different Aftercool factory.', $product->sourceArtikelnummer, $product->ean, $product->rowNo);
                unset($products[$sourceProductId]);
            }
            $records = array_values(array_filter($records, static fn (AfterCoolProductWriteRecord $record): bool => isset($products[$record->sourceProductId])));
            $writeResult = $this->writer->write($records, $context);
            $issues = $this->appendWriteFailures($issues, $products, $writeResult->failures);
            $successful = array_fill_keys($writeResult->successfulSourceProductIds, true);
            $successfulProducts = array_filter(
                $products,
                static fn (AfterCoolPreparedProduct $prepared): bool => isset($successful[$prepared->product->sourceProductId]),
            );

            $factoryId = $this->persistFactory($run, $successfulProducts, $context);
            if (null !== $factoryId) {
                $this->productRepository->update(array_values(array_map(
                    static fn (AfterCoolPreparedProduct $prepared): array => ['id' => $prepared->productId, 'jvFactoryId' => $factoryId],
                    $successfulProducts,
                )), $context);
            }
            $this->upsertSourceLinks($run, $successfulProducts, $context);
            $this->upsertRunProducts($run, $successfulProducts, $context);
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

    /** @param array<string, AfterCoolPreparedProduct> $products */
    private function lockProductsForFactoryCheck(array $products): void
    {
        $ids = array_values(array_unique(array_map(
            static fn (AfterCoolPreparedProduct $product): string => $product->productId,
            $products,
        )));
        if ([] === $ids) {
            return;
        }

        sort($ids, SORT_STRING);
        $this->connection->fetchFirstColumn(
            'SELECT id FROM product WHERE id IN (?) AND version_id = ? ORDER BY id FOR UPDATE',
            [array_map(Uuid::fromHexToBytes(...), $ids), Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            [\Doctrine\DBAL\ArrayParameterType::BINARY, \Doctrine\DBAL\ParameterType::BINARY],
        );
    }

    /** @param array<string, AfterCoolPreparedProduct> $products
     * @return list<string> */
    private function conflictingFactoryProductIds(AfterCoolImportRunEntity $run, array $products, Context $context): array
    {
        if ([] === $products) {
            return [];
        }
        $ids = array_values(array_unique(array_map(static fn (AfterCoolPreparedProduct $product): string => $product->productId, $products)));
        $criteria = (new Criteria())->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter('productId', $ids));
        $conflictingProducts = [];
        foreach ($this->sourceRepository->search($criteria, $context)->getEntities() as $source) {
            if ($source->getFactoryId() !== $run->getFactoryId()) {
                $conflictingProducts[$source->getProductId()] = true;
            }
        }

        $conflicts = [];
        foreach ($products as $sourceProductId => $prepared) {
            if (isset($conflictingProducts[$prepared->productId])) {
                $conflicts[] = $sourceProductId;
            }
        }

        return $conflicts;
    }

    /** @param array<string, AfterCoolPreparedProduct> $products */
    private function persistFactory(AfterCoolImportRunEntity $run, array $products, Context $context): ?string
    {
        if ([] === $products) {
            return null;
        }
        $namespace = 'aftercool:'.$run->getAccount().':'.$run->getDataset();
        $externalId = (string) $run->getFactoryId();
        $criteria = (new Criteria())->addFilter(new EqualsFilter('sourceNamespace', $namespace))->addFilter(new EqualsFilter('externalId', $externalId));
        $source = $this->factorySourceRepository->search($criteria, $context)->first();
        if (null !== $source) {
            $factoryId = $source->getFactoryId();
            $this->factoryRepository->update([['id' => $factoryId, 'name' => $run->getFactoryName()]], $context);

            return $factoryId;
        }

        $factoryId = Uuid::randomHex();
        $this->factoryRepository->create([['id' => $factoryId, 'name' => $run->getFactoryName()]], $context);
        $this->factorySourceRepository->create([[
            'id' => Uuid::randomHex(),
            'sourceNamespace' => $namespace,
            'externalId' => $externalId,
            'factoryId' => $factoryId,
        ]], $context);

        return $factoryId;
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
    private function upsertSourceLinks(AfterCoolImportRunEntity $run, array $products, Context $context): void
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
                'factoryName' => $run->getFactoryName(),
                'sourceProductId' => $prepared->product->sourceProductId,
                'productId' => $prepared->productId,
                'productVersionId' => Defaults::LIVE_VERSION,
                'sourceArtikelnummer' => $prepared->product->sourceArtikelnummer,
                'sourceEan' => $prepared->product->ean,
                'stammartikelId' => $prepared->product->stammartikelId,
                'collectionName' => $prepared->product->collectionName,
                'sourceFile' => $prepared->product->sourceFile,
                'sourceFilePrefix' => $prepared->product->sourceFilePrefix,
                'sourceRegion' => $prepared->product->sourceRegion,
                'lastSeenAt' => $now,
            ],
            $products,
        )), $context);
    }

    /** @param array<string, AfterCoolPreparedProduct> $products */
    private function upsertRunProducts(AfterCoolImportRunEntity $run, array $products, Context $context): void
    {
        if ([] === $products) {
            return;
        }

        $this->runProductRepository->upsert(array_values(array_map(
            static fn (AfterCoolPreparedProduct $prepared): array => [
                'id' => Uuid::fromStringToHex('jvmoebel.aftercool.run-product.'.$run->getId().'.'.$prepared->productId),
                'runId' => $run->getId(),
                'productId' => $prepared->productId,
                'productVersionId' => Defaults::LIVE_VERSION,
                'productNumber' => $prepared->product->ean,
                'sourceEan' => $prepared->product->ean,
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
