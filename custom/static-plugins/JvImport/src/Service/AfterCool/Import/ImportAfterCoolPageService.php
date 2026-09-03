<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Import;

use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunEntity;
use Jv\Import\Service\AfterCool\Contract\AfterCoolImportProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolPageProcessingResult;
use Jv\Import\Service\AfterCool\Dto\AfterCoolPreparedProduct;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductIssue;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductWriteRecord;
use Jv\Import\Service\AfterCool\Exception\AfterCoolProductWriteValidationException;
use Jv\Import\Service\AfterCool\Exception\AfterCoolUnexpectedPageOffsetException;
use Jv\Import\Service\AfterCool\Media\ProcessAfterCoolStagedMediaService;
use Jv\Import\Service\AfterCool\Persistence\AfterCoolPageCheckpointService;
use Jv\Import\Service\ProductImport\ResolveDefaultProductTaxService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Lock\LockFactory;

/**
 * Owns one idempotent Aftercool page checkpoint. Upstream access and mapping
 * remain in Integration; this class only applies the import use case.
 */
readonly class ImportAfterCoolPageService
{
    /**
     * @param EntityRepository<AfterCoolImportRunCollection> $runRepository
     */
    public function __construct(
        private AfterCoolImportProductSourceInterface $source,
        private ResolveAfterCoolProductPageService $pageResolver,
        private BuildAfterCoolShopwareProductRecordService $recordBuilder,
        private AfterCoolPageCheckpointService $checkpoint,
        private ProcessAfterCoolStagedMediaService $stagedMediaProcessor,
        private ResolveDefaultProductTaxService $defaultTax,
        private EntityRepository $runRepository,
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

        $page = $this->source->getImportProductPage($run->getFactoryId(), $offset);
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
                $products[$product->sourceProductId] = new AfterCoolPreparedProduct(
                    $product,
                    $resolvedProduct,
                    $payload['id'],
                    $payload,
                );
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

        $this->checkpoint->checkpoint($run, $offset, $page->total, $page->hasMore, $records, $products, $issues, $context);

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
}
