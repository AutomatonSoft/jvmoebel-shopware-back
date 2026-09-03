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
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
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
     * @param EntityRepository<ProductCollection>                $productRepository
     * @param EntityRepository<AfterCoolImportErrorCollection>   $errorRepository
     */
    public function __construct(
        private AfterCoolProductSourceInterface $source,
        private BuildAfterCoolShopwareProductRecordService $recordBuilder,
        private AfterCoolSyncBatchWriter $writer,
        private AfterCoolExternalMediaLinkService $mediaLinks,
        private ResolveDefaultProductTaxService $defaultTax,
        private EntityRepository $runRepository,
        private EntityRepository $sourceRepository,
        private EntityRepository $productRepository,
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

        foreach ($mapping->products as $product) {
            $existingProductId = $this->resolveProductId($product, $context, $issues);
            if (false === $existingProductId) {
                continue;
            }

            try {
                $payload = $this->recordBuilder->build(
                    $product,
                    $existingProductId,
                    $tax->id,
                    $tax->rate,
                    Defaults::CURRENCY,
                    Market::Germany->languageId(),
                    null === $existingProductId ? [] : $this->existingPrices($existingProductId, $context),
                );
                $records[] = new AfterCoolProductWriteRecord(
                    $product->sourceProductId,
                    $payload,
                );
                $products[$product->sourceProductId] = [$product, null === $existingProductId, $payload['id']];
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
            $recordsWithMedia = [];
            foreach ($records as $record) {
                [$product, , $productId] = $products[$record->sourceProductId];
                $payload = $record->payload;
                $media = $this->mediaLinks->link($productId, $product->mediaUrls, $this->existingCoverId($productId, $context), $context);
                if ([] !== $media->productMedia) {
                    $payload['media'] = $media->productMedia;
                }
                if (null !== $media->coverId) {
                    $payload['coverId'] = $media->coverId;
                }
                foreach ($media->issues as $mediaIssue) {
                    $issues[] = new AfterCoolProductIssue($product->sourceProductId, 'failed', $mediaIssue->code, $mediaIssue->message, $product->sourceArtikelnummer, $product->ean, $product->rowNo, false);
                }
                $recordsWithMedia[] = new AfterCoolProductWriteRecord($record->sourceProductId, $payload);
            }

            $writeResult = $this->writer->write($recordsWithMedia, $context);
            foreach ($writeResult->failures as $failure) {
                [$product] = $products[$failure->sourceProductId] ?? [null];
                $issues[] = new AfterCoolProductIssue($failure->sourceProductId, 'failed', $failure->code, $failure->message, $product?->sourceArtikelnummer, $product?->ean, $product?->rowNo);
            }
            $successful = array_fill_keys($writeResult->successfulSourceProductIds, true);
            $created = 0;
            $updated = 0;
            foreach ($products as $sourceProductId => [$product, $isNew, $productId]) {
                if (!isset($successful[$sourceProductId])) {
                    continue;
                }
                $this->upsertSourceLink($product, $productId, $context);
                if ($isNew) {
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

    private function existingCoverId(?string $productId, Context $context): ?string
    {
        if (null === $productId) {
            return null;
        }
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        return $product instanceof ProductEntity ? $product->getCoverId() : null;
    }

    /** @return list<array<string, mixed>> */
    private function existingPrices(string $productId, Context $context): array
    {
        $product = $this->productRepository->search((new Criteria([$productId]))->addAssociation('price'), $context)->first();
        if (!$product instanceof ProductEntity || null === $product->getPrice()) {
            return [];
        }

        return array_map(fn (Price $price): array => $this->priceData($price), $product->getPrice()->getElements());
    }

    /** @return array<string, mixed> */
    private function priceData(Price $price): array
    {
        $data = [
            'currencyId' => $price->getCurrencyId(),
            'net' => $price->getNet(),
            'gross' => $price->getGross(),
            'linked' => $price->getLinked(),
        ];
        if (null !== $price->getListPrice()) {
            $data['listPrice'] = $this->nestedPriceData($price->getListPrice());
        }
        if (null !== $price->getRegulationPrice()) {
            $data['regulationPrice'] = $this->nestedPriceData($price->getRegulationPrice());
        }

        return $data;
    }

    /** @return array{net: float, gross: float, linked: bool} */
    private function nestedPriceData(Price $price): array
    {
        return ['net' => $price->getNet(), 'gross' => $price->getGross(), 'linked' => $price->getLinked()];
    }

    /** @param list<AfterCoolProductIssue> $issues */
    private function resolveProductId(AfterCoolMappedProduct $product, Context $context, array &$issues): string|false|null
    {
        $sourceCriteria = (new Criteria())->addFilter(new EqualsFilter('account', $product->account));
        $sourceCriteria->addFilter(new EqualsFilter('dataset', $product->dataset));
        $sourceCriteria->addFilter(new EqualsFilter('factoryId', $product->factoryId));
        $sourceCriteria->addFilter(new EqualsFilter('sourceProductId', $product->sourceProductId));
        $source = $this->sourceRepository->search($sourceCriteria, $context)->first();
        if (null !== $source) {
            if ($source->getSourceEan() !== $product->ean || $source->getSourceArtikelnummer() !== $product->sourceArtikelnummer) {
                $issues[] = new AfterCoolProductIssue(
                    $product->sourceProductId,
                    'skipped',
                    'source_identity_conflict',
                    'Aftercool source identity conflicts with its recorded EAN or Artikelnummer.',
                    $product->sourceArtikelnummer,
                    $product->ean,
                    $product->rowNo,
                );

                return false;
            }
            $linkedProductId = $source->getProductId();
            if ($this->productRepository->searchIds(new Criteria([$linkedProductId]), $context)->has($linkedProductId)) {
                return $linkedProductId;
            }
            $issues[] = new AfterCoolProductIssue(
                $product->sourceProductId,
                'skipped',
                'missing_linked_product',
                'Aftercool source link points to a missing product.',
                $product->sourceArtikelnummer,
                $product->ean,
                $product->rowNo,
            );

            return false;
        }

        $sourceArtikelnummerCriteria = $this->sourceIdentityCriteria($product);
        $sourceArtikelnummerCriteria->addFilter(new EqualsFilter('sourceArtikelnummer', $product->sourceArtikelnummer));
        $sourceWithArtikelnummer = $this->sourceRepository->search($sourceArtikelnummerCriteria, $context)->first();
        if (null !== $sourceWithArtikelnummer && $sourceWithArtikelnummer->getId() !== $this->sourceIdentityId($product)) {
            $issues[] = new AfterCoolProductIssue(
                $product->sourceProductId,
                'skipped',
                'source_artikelnummer_conflict',
                'Aftercool Artikelnummer is already used by another source identity.',
                $product->sourceArtikelnummer,
                $product->ean,
                $product->rowNo,
            );

            return false;
        }

        $sourceEanCriteria = $this->sourceIdentityCriteria($product);
        $sourceEanCriteria->addFilter(new EqualsFilter('sourceEan', $product->ean));
        $sourceWithEan = $this->sourceRepository->search($sourceEanCriteria, $context)->first();
        if (null !== $sourceWithEan && $sourceWithEan->getId() !== $this->sourceIdentityId($product)) {
            $issues[] = new AfterCoolProductIssue($product->sourceProductId, 'skipped', 'duplicate_ean_in_factory', 'Duplicate EAN in Aftercool factory.', $product->sourceArtikelnummer, $product->ean, $product->rowNo);

            return false;
        }

        $criteria = (new Criteria())->addFilter(new EqualsFilter('productNumber', $product->productNumber));
        $ids = $this->productRepository->searchIds($criteria, $context)->getIds();
        if (1 < count($ids)) {
            $issues[] = new AfterCoolProductIssue(
                $product->sourceProductId,
                'skipped',
                'ambiguous_product_number',
                'Multiple Shopware products have this EAN.',
                $product->sourceArtikelnummer,
                $product->ean,
                $product->rowNo,
            );

            return false;
        }

        return $ids[0] ?? null;
    }

    private function sourceIdentityCriteria(AfterCoolMappedProduct $product): Criteria
    {
        return (new Criteria())
            ->addFilter(new EqualsFilter('account', $product->account))
            ->addFilter(new EqualsFilter('dataset', $product->dataset))
            ->addFilter(new EqualsFilter('factoryId', $product->factoryId));
    }

    private function sourceIdentityId(AfterCoolMappedProduct $product): string
    {
        return Uuid::fromStringToHex(implode(':', ['jvmoebel.aftercool.source', $product->account, $product->dataset, $product->factoryId, $product->sourceProductId]));
    }

    private function upsertSourceLink(AfterCoolMappedProduct $product, string $productId, Context $context): void
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('account', $product->account));
        $criteria->addFilter(new EqualsFilter('dataset', $product->dataset));
        $criteria->addFilter(new EqualsFilter('factoryId', $product->factoryId));
        $criteria->addFilter(new EqualsFilter('sourceProductId', $product->sourceProductId));
        $existing = $this->sourceRepository->search($criteria, $context)->first();
        $sourceId = null === $existing ? $this->sourceIdentityId($product) : $existing->getId();
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
