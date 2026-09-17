<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Seo;

use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\Import\Integration\Csv\SemicolonCsvReader;
use Jv\Import\Service\ProductImport\Seo\Dto\CosmoShopProductRedirectImportResult;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Jv\Seo\Contract\ImportProductRedirectData;
use Jv\Seo\Contract\ImportProductRedirectsInterface;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Component\Lock\LockFactory;

final readonly class ImportCosmoShopProductRedirectsService
{
    /** @param EntityRepository<ProductCollection> $productRepository */
    public function __construct(
        private ImportExportService $importExportService,
        private FilesystemOperator $privateFilesystem,
        private SemicolonCsvReader $csvReader,
        private EntityRepository $productRepository,
        private ?ImportProductRedirectsInterface $redirectImporter,
        private LockFactory $lockFactory,
        private string $projectDir,
    ) {
    }

    public function execute(string $sourceImportLogId, Context $context): CosmoShopProductRedirectImportResult
    {
        $lock = $this->lockFactory->createLock('jv_product_redirect_import_'.$sourceImportLogId, 7200.0);
        if (!$lock->acquire()) {
            return new CosmoShopProductRedirectImportResult(0, 0, 0, 0, 0, 0, 0, 0, 0, []);
        }

        try {
            return $this->import($sourceImportLogId, $context, static fn () => $lock->refresh(7200.0));
        } finally {
            $lock->release();
        }
    }

    private function import(string $sourceImportLogId, Context $context, \Closure $refreshLock): CosmoShopProductRedirectImportResult
    {
        if (null === $this->redirectImporter) {
            throw new \RuntimeException('JvSeo must be active before product legacy redirects can be imported.');
        }

        $sourceLog = $this->importExportService->findLog($context, $sourceImportLogId);
        $market = $this->market($sourceLog);
        $sourceFile = $sourceLog->getFile();
        if (null === $sourceFile) {
            throw new \InvalidArgumentException(sprintf('CosmoShop import log "%s" has no source file.', $sourceImportLogId));
        }

        $directory = $this->createTemporaryDirectory($sourceImportLogId);
        try {
            $sourceCsv = $directory.'/source.csv';
            $this->copySourceFile($sourceFile->getPath(), $sourceCsv);
            $invalidProductNumbers = $this->invalidProductNumbers($sourceLog, $context, $directory);
            $sourceRows = [];
            foreach ($this->csvReader->rows($sourceCsv, ['product_number']) as $row) {
                $productNumber = trim($row['product_number']);
                if ('' === $productNumber || isset($invalidProductNumbers[$productNumber])) {
                    continue;
                }
                $sourceRows[$productNumber] = $row;
            }

            $created = $updated = $unchanged = $manualPreserved = $conflicts = $invalid = 0;
            $missingSourceIdentity = $missingProduct = 0;
            $issues = [];
            foreach (array_chunk($sourceRows, 500, true) as $rows) {
                $refreshLock();
                $products = $this->products(array_keys($rows), $context);
                $batch = [];
                foreach ($rows as $productNumber => $row) {
                    $sourceIdentifier = trim($row['source_article_id'] ?? '');
                    $legacyUrl = $this->legacyUrl($market, $row);
                    if ('' === $sourceIdentifier) {
                        ++$missingSourceIdentity;
                        $this->appendIssue($issues, $sourceIdentifier, $legacyUrl, 'missing_source_identity', sprintf('Product "%s" has no source_article_id.', $productNumber));
                        continue;
                    }
                    $product = $products[$productNumber] ?? null;
                    if (!$product instanceof ProductEntity) {
                        ++$missingProduct;
                        $this->appendIssue($issues, $sourceIdentifier, $legacyUrl, 'missing_product', sprintf('Shopware product "%s" was not found.', $productNumber));
                        continue;
                    }
                    if ('' === $legacyUrl) {
                        ++$invalid;
                        $this->appendIssue($issues, $sourceIdentifier, '', 'missing_legacy_url', sprintf('Product "%s" has neither legacy_url nor urlkey.', $productNumber));
                        continue;
                    }

                    $batch[] = new ImportProductRedirectData(
                        'cosmoshop',
                        $market->domain(),
                        $sourceIdentifier,
                        $product->getId(),
                        $market->salesChannelId(),
                        $legacyUrl,
                    );
                }

                if ([] === $batch) {
                    continue;
                }
                $result = $this->redirectImporter->import($batch, $context);
                $created += $result->created;
                $updated += $result->updated;
                $unchanged += $result->unchanged;
                $manualPreserved += $result->manualPreserved;
                $conflicts += $result->conflicts;
                $invalid += $result->invalid;
                foreach ($result->issues as $issue) {
                    $this->appendIssue($issues, $issue['sourceIdentifier'], $issue['sourceUrl'], $issue['code'], $issue['message']);
                }
            }

            return new CosmoShopProductRedirectImportResult(
                count($sourceRows),
                $created,
                $updated,
                $unchanged,
                $manualPreserved,
                $conflicts,
                $invalid,
                $missingSourceIdentity,
                $missingProduct,
                $issues,
            );
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function market(ImportExportLogEntity $log): Market
    {
        $market = ImportExportLogEntity::ACTIVITY_IMPORT === $log->getActivity()
            ? MarketImportProfile::marketForTechnicalName($log->getProfile()?->getTechnicalName())
            : null;
        if (!$market instanceof Market) {
            throw new \InvalidArgumentException(sprintf('Import log "%s" is not a CosmoShop product import.', $log->getId()));
        }

        return $market;
    }

    /**
     * @param list<string> $productNumbers
     *
     * @return array<string, ProductEntity>
     */
    private function products(array $productNumbers, Context $context): array
    {
        if ([] === $productNumbers) {
            return [];
        }
        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('productNumber', $productNumbers))
            ->setLimit(count($productNumbers));
        $products = [];
        foreach ($this->productRepository->search($criteria, $context) as $product) {
            $products[$product->getProductNumber()] = $product;
        }

        return $products;
    }

    /** @param array<string, string> $row */
    private function legacyUrl(Market $market, array $row): string
    {
        $legacyUrl = trim($row['legacy_url'] ?? '');
        if ('' !== $legacyUrl) {
            return $legacyUrl;
        }

        $urlKey = trim($row['urlkey'] ?? '');
        if ('' === $urlKey) {
            return '';
        }

        return 'https://www.'.$market->domain().'/'.$urlKey.(str_ends_with(strtolower($urlKey), '.htm') ? '' : '.htm');
    }

    /** @return array<string, true> */
    private function invalidProductNumbers(ImportExportLogEntity $sourceLog, Context $context, string $directory): array
    {
        if (null === $sourceLog->getInvalidRecordsLogId()) {
            return [];
        }
        $invalidLog = $this->importExportService->findLog($context, $sourceLog->getInvalidRecordsLogId());
        if (null === $invalidLog->getFile()) {
            return [];
        }
        $invalidCsv = $directory.'/invalid.csv';
        $this->copySourceFile($invalidLog->getFile()->getPath(), $invalidCsv);
        $invalidProductNumbers = [];
        foreach ($this->csvReader->rows($invalidCsv, ['product_number']) as $row) {
            $invalidProductNumbers[trim($row['product_number'])] = true;
        }

        return $invalidProductNumbers;
    }

    private function createTemporaryDirectory(string $sourceImportLogId): string
    {
        $baseDirectory = $this->projectDir.'/var/import/seo-redirects';
        if (!is_dir($baseDirectory) && !mkdir($baseDirectory, 0775, true) && !is_dir($baseDirectory)) {
            throw new \RuntimeException(sprintf('Could not create SEO redirect import directory "%s".', $baseDirectory));
        }
        $directory = $baseDirectory.'/'.$sourceImportLogId.'-'.bin2hex(random_bytes(8));
        if (!mkdir($directory, 0775)) {
            throw new \RuntimeException(sprintf('Could not create SEO redirect temporary directory "%s".', $directory));
        }

        return $directory;
    }

    private function copySourceFile(string $path, string $target): void
    {
        $source = $this->privateFilesystem->readStream($path);
        if (!is_resource($source)) {
            throw new \RuntimeException(sprintf('Could not read import file "%s".', $path));
        }
        $destination = fopen($target, 'w+b');
        if (!is_resource($destination)) {
            fclose($source);
            throw new \RuntimeException(sprintf('Could not create temporary file "%s".', $target));
        }
        try {
            stream_copy_to_stream($source, $destination);
        } finally {
            fclose($source);
            fclose($destination);
        }
    }

    /**
     * @param list<array{sourceIdentifier: string, sourceUrl: string, code: string, message: string}> $issues
     */
    private function appendIssue(array &$issues, string $sourceIdentifier, string $sourceUrl, string $code, string $message): void
    {
        if (count($issues) >= 50) {
            return;
        }
        $issues[] = compact('sourceIdentifier', 'sourceUrl', 'code', 'message');
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $items = scandir($directory);
        if (false === $items) {
            return;
        }
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $directory.'/'.$item;
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
