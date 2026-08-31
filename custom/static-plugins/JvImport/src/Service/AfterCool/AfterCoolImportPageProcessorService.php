<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

use Doctrine\DBAL\Connection;
use Jv\Import\Core\Content\AfterCoolImportError\AfterCoolImportErrorCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunEntity;
use Jv\Import\Core\Content\AfterCoolProductSource\AfterCoolProductSourceCollection;
use Jv\Import\Integration\AfterCool\AfterCoolApiClientInterface;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolMappedProduct;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductIssue;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolProductPageMapper;
use Jv\Import\Service\AfterCool\Contract\AfterCoolImportPageProcessor;
use Jv\Import\Service\AfterCool\Exception\AfterCoolProductWriteValidationException;
use Jv\Import\Service\AfterCool\Exception\AfterCoolUnexpectedPageOffsetException;
use Jv\Import\Service\ProductImport\ResolveDefaultProductTaxService;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

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
        private AfterCoolApiClientInterface $client,
        private AfterCoolProductPageMapper $pageMapper,
        private BuildAfterCoolShopwareProductRecordService $recordBuilder,
        private AfterCoolSyncBatchWriter $writer,
        private AfterCoolExternalMediaLinkService $mediaLinks,
        private ResolveDefaultProductTaxService $defaultTax,
        private EntityRepository $runRepository,
        private EntityRepository $sourceRepository,
        private EntityRepository $productRepository,
        private EntityRepository $errorRepository,
        private Connection $connection,
    ) {
    }

    public function process(string $runId, int $offset, Context $context): AfterCoolPageProcessingResult
    {
        $run = $this->loadRun($runId, $context);
        if ($offset < $run->getNextOffset()) {
            return AfterCoolPageProcessingResult::completed();
        }
        if ($offset > $run->getNextOffset()) {
            throw new AfterCoolUnexpectedPageOffsetException();
        }

        $page = $this->client->getProductPage($run->getFactoryId(), $offset);
        $mapping = $this->pageMapper->map($page);
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
                    Defaults::LANGUAGE_SYSTEM,
                );
                $existingCoverId = $this->existingCoverId($existingProductId, $context);
                $media = $this->mediaLinks->link($payload['id'], $product->mediaUrls, $existingCoverId, $context);
                if ([] !== $media->productMedia) {
                    $payload['media'] = $media->productMedia;
                }
                if (null !== $media->coverId) {
                    $payload['coverId'] = $media->coverId;
                }
                foreach ($media->issues as $mediaIssue) {
                    $issues[] = new AfterCoolProductIssue($product->sourceProductId, 'failed', $mediaIssue->code, $mediaIssue->message);
                }
                $records[] = new AfterCoolProductWriteRecord(
                    $product->sourceProductId,
                    $payload,
                );
                $products[$product->sourceProductId] = [$product, null === $existingProductId, $payload['id']];
            } catch (AfterCoolProductWriteValidationException $exception) {
                $issues[] = new AfterCoolProductIssue($product->sourceProductId, 'failed', $exception->safeCode(), 'Aftercool product cannot be created without a valid price.');
            }
        }

        $writeResult = $this->writer->write($records, $context);
        foreach ($writeResult->failures as $failure) {
            $issues[] = new AfterCoolProductIssue($failure->sourceProductId, 'failed', $failure->code, $failure->message);
        }

        $successful = array_fill_keys($writeResult->successfulSourceProductIds, true);
        $this->connection->transactional(function () use ($run, $page, $offset, $products, $successful, $issues, $context): void {
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
                if ('invalid_media_url' !== $issue->code && 'external_media_link_failed' !== $issue->code) {
                    'skipped' === $issue->result ? ++$skipped : ++$failed;
                }
                $this->recordIssue($run, $offset, $issue, $context);
            }

            $processed = $created + $updated + $skipped + $failed;
            $terminal = !$page->hasMore;
            $status = $terminal ? (0 < $run->getFailed() + $failed ? 'completed_with_errors' : 'completed') : 'running';
            $payload = [
                'id' => $run->getId(),
                'status' => $status,
                'total' => $run->getTotal() ?? $page->total,
                'nextOffset' => $offset + 100,
                'processed' => $run->getProcessed() + $processed,
                'created' => $run->getCreated() + $created,
                'updated' => $run->getUpdated() + $updated,
                'skipped' => $run->getSkipped() + $skipped,
                'failed' => $run->getFailed() + $failed,
            ];
            if ($terminal) {
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

    /** @param list<AfterCoolProductIssue> $issues */
    private function resolveProductId(AfterCoolMappedProduct $product, Context $context, array &$issues): string|false|null
    {
        $sourceCriteria = (new Criteria())->addFilter(new EqualsFilter('account', $product->account));
        $sourceCriteria->addFilter(new EqualsFilter('dataset', $product->dataset));
        $sourceCriteria->addFilter(new EqualsFilter('factoryId', $product->factoryId));
        $sourceCriteria->addFilter(new EqualsFilter('sourceProductId', $product->sourceProductId));
        $source = $this->sourceRepository->search($sourceCriteria, $context)->first();
        if (null !== $source) {
            $linkedProductId = $source->getProductId();
            if ($this->productRepository->searchIds(new Criteria([$linkedProductId]), $context)->has($linkedProductId)) {
                return $linkedProductId;
            }
            $issues[] = new AfterCoolProductIssue($product->sourceProductId, 'skipped', 'missing_linked_product', 'Aftercool source link points to a missing product.');

            return false;
        }

        $criteria = (new Criteria())->addFilter(new EqualsFilter('productNumber', $product->productNumber));
        $ids = $this->productRepository->searchIds($criteria, $context)->getIds();
        if (1 < count($ids)) {
            $issues[] = new AfterCoolProductIssue($product->sourceProductId, 'skipped', 'ambiguous_product_number', 'Multiple Shopware products have this EAN.');

            return false;
        }

        return $ids[0] ?? null;
    }

    private function upsertSourceLink(AfterCoolMappedProduct $product, string $productId, Context $context): void
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('account', $product->account));
        $criteria->addFilter(new EqualsFilter('dataset', $product->dataset));
        $criteria->addFilter(new EqualsFilter('factoryId', $product->factoryId));
        $criteria->addFilter(new EqualsFilter('sourceProductId', $product->sourceProductId));
        $existing = $this->sourceRepository->search($criteria, $context)->first();
        $sourceId = null === $existing
            ? Uuid::fromStringToHex(implode(':', ['jvmoebel.aftercool.source', $product->account, $product->dataset, $product->factoryId, $product->sourceProductId]))
            : $existing->getId();
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
            'offset' => $offset,
            'result' => $issue->result,
            'code' => $issue->code,
            'message' => $issue->message,
            'createdAt' => new \DateTimeImmutable(),
        ]], $context);
    }
}
