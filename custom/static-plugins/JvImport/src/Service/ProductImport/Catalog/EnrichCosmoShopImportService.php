<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\Import\Integration\Csv\SemicolonCsvReader;
use Jv\Import\Integration\Okb\Profile\CatalogProductImportProfile;
use Jv\Import\Integration\Okb\Service\PrepareOkbProductMappingService;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogCollection;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class EnrichCosmoShopImportService
{
    private const string SOURCE_IMPORT_LOG_PARAMETER = 'jvCatalogEnrichmentSourceImportLogId';

    public function __construct(
        private ImportExportService $importExportService,
        private FilesystemOperator $privateFilesystem,
        private SemicolonCsvReader $csvReader,
        private PrepareOkbProductMappingService $mappingService,
        private PrepareCatalogShopwareImportCsvService $csvService,
        /** @var EntityRepository<EntityCollection<\Shopware\Core\Content\ImportExport\ImportExportProfileEntity>> */
        private EntityRepository $profileRepository,
        /** @var EntityRepository<ImportExportLogCollection> */
        private EntityRepository $logRepository,
        private MessageBusInterface $messageBus,
        private LockFactory $lockFactory,
        private string $projectDir,
    ) {
    }

    public function execute(string $sourceImportLogId, Context $context): void
    {
        $lock = $this->lockFactory->createLock('jv_catalog_enrichment_'.$sourceImportLogId, 7200.0);
        if (!$lock->acquire()) {
            return;
        }
        try {
            $catalogLogId = $this->catalogImportLogId($sourceImportLogId, $context);
            if (null !== $catalogLogId) {
                $catalogLog = $this->importExportService->findLog($context, $catalogLogId);
                if (Progress::STATE_PROGRESS === $catalogLog->getState()) {
                    $this->messageBus->dispatch(new ImportExportMessage($context, $catalogLogId, ImportExportLogEntity::ACTIVITY_IMPORT));
                }

                return;
            }
            $this->enrich($sourceImportLogId, $context, static fn () => $lock->refresh(7200.0));
        } finally {
            $lock->release();
        }
    }

    private function enrich(string $sourceImportLogId, Context $context, \Closure $refreshLock): void
    {
        $sourceLog = $this->importExportService->findLog($context, $sourceImportLogId);
        $this->assertSourceLog($sourceLog);
        $sourceFile = $sourceLog->getFile();
        if (null === $sourceFile) {
            throw new \InvalidArgumentException(sprintf('CosmoShop import log "%s" has no source file.', $sourceImportLogId));
        }

        $directory = $this->createTemporaryDirectory($sourceImportLogId);
        try {
            $sourceCsv = $directory.'/source.csv';
            $this->copySourceFile($sourceFile->getPath(), $sourceCsv);
            $mappingSourceCsv = $this->withoutInvalidSourceRows($sourceCsv, $sourceLog, $context, $directory);
            $mappingDirectory = $directory.'/mapping';
            if (!mkdir($mappingDirectory, 0775) && !is_dir($mappingDirectory)) {
                throw new \RuntimeException(sprintf('Could not create OKB mapping directory "%s".', $mappingDirectory));
            }
            $this->mappingService->execute($mappingSourceCsv, $this->projectDir.'/data/import/okb', $mappingDirectory, null, $refreshLock);
            $catalogCsv = $directory.'/catalog-products.csv';
            $this->csvService->execute(
                $mappingDirectory.'/okb-product-mapping.csv',
                $mappingDirectory.'/okb-product-attributes.csv',
                $catalogCsv,
                $mappingDirectory.'/okb-product-mapping-failures.csv',
            );
            $this->profileRepository->upsert([CatalogProductImportProfile::definition()], $context);
            $catalogLog = $this->importExportService->prepareImport(
                $context,
                CatalogProductImportProfile::definition()['id'],
                new \DateTimeImmutable('+1 day'),
                new UploadedFile($catalogCsv, 'okb-catalog-products.csv', 'text/csv', null, true),
                ['parameters' => [self::SOURCE_IMPORT_LOG_PARAMETER => $sourceImportLogId]],
            );
            $this->messageBus->dispatch(new ImportExportMessage($context, $catalogLog->getId(), $catalogLog->getActivity()));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function catalogImportLogId(string $sourceImportLogId, Context $context): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('profileId', CatalogProductImportProfile::definition()['id']))
            ->addFilter(new EqualsFilter('config.parameters.'.self::SOURCE_IMPORT_LOG_PARAMETER, $sourceImportLogId))
            ->setLimit(1);

        return $this->logRepository->searchIds($criteria, $context)->firstId();
    }

    private function assertSourceLog(ImportExportLogEntity $log): void
    {
        if (
            ImportExportLogEntity::ACTIVITY_IMPORT !== $log->getActivity()
            || null === MarketImportProfile::marketForTechnicalName($log->getProfile()?->getTechnicalName())
        ) {
            throw new \InvalidArgumentException(sprintf('Import log "%s" is not a CosmoShop product import.', $log->getId()));
        }
    }

    private function createTemporaryDirectory(string $sourceImportLogId): string
    {
        $baseDirectory = $this->projectDir.'/var/import/okb-enrichment';
        if (!is_dir($baseDirectory) && !mkdir($baseDirectory, 0775, true) && !is_dir($baseDirectory)) {
            throw new \RuntimeException(sprintf('Could not create OKB enrichment base directory "%s".', $baseDirectory));
        }
        $directory = $baseDirectory.'/'.$sourceImportLogId.'-'.bin2hex(random_bytes(8));
        if (!mkdir($directory, 0775)) {
            throw new \RuntimeException(sprintf('Could not create OKB enrichment directory "%s".', $directory));
        }

        return $directory;
    }

    private function copySourceFile(string $path, string $target): void
    {
        $source = $this->privateFilesystem->readStream($path);
        if (!is_resource($source)) {
            throw new \RuntimeException(sprintf('Could not read source import file "%s".', $path));
        }
        $destination = fopen($target, 'w+b');
        if (!is_resource($destination)) {
            fclose($source);
            throw new \RuntimeException(sprintf('Could not create temporary source file "%s".', $target));
        }

        try {
            stream_copy_to_stream($source, $destination);
        } finally {
            fclose($source);
            fclose($destination);
        }
    }

    private function withoutInvalidSourceRows(string $sourceCsv, ImportExportLogEntity $sourceLog, Context $context, string $directory): string
    {
        $invalidLogId = $sourceLog->getInvalidRecordsLogId();
        if (null === $invalidLogId) {
            return $sourceCsv;
        }
        $invalidLog = $this->importExportService->findLog($context, $invalidLogId);
        $invalidFile = $invalidLog->getFile();
        if (null === $invalidFile) {
            throw new \InvalidArgumentException(sprintf('Invalid-record log "%s" has no file.', $invalidLogId));
        }
        $invalidCsv = $directory.'/source-invalid.csv';
        $this->copySourceFile($invalidFile->getPath(), $invalidCsv);
        $invalidProductNumbers = [];
        foreach ($this->csvReader->rows($invalidCsv, ['product_number']) as $row) {
            $invalidProductNumbers[$row['product_number']] = true;
        }
        if ([] === $invalidProductNumbers) {
            return $sourceCsv;
        }

        $filtered = $directory.'/source-valid.csv';
        $input = fopen($sourceCsv, 'rb');
        $output = fopen($filtered, 'wb');
        if (false === $input || false === $output) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            throw new \RuntimeException('Could not prepare the valid source rows for catalog enrichment.');
        }
        try {
            $headers = fgetcsv($input, 0, ';', '"', '\\');
            if (false === $headers || false === fputcsv($output, $headers, ';', '"', '\\')) {
                throw new \RuntimeException('Could not read the source CSV header for catalog enrichment.');
            }
            $productNumberColumn = array_search('product_number', array_map(static fn (string $header): string => trim($header), $headers), true);
            if (false === $productNumberColumn) {
                throw new \InvalidArgumentException('Source CSV has no product_number column.');
            }
            while (false !== ($row = fgetcsv($input, 0, ';', '"', '\\'))) {
                if (!isset($invalidProductNumbers[trim($row[$productNumberColumn] ?? '')]) && false === fputcsv($output, $row, ';', '"', '\\')) {
                    throw new \RuntimeException('Could not write valid source rows for catalog enrichment.');
                }
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        return $filtered;
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
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
