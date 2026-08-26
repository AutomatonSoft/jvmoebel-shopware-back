<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Integration\CosmoShop\Reader\CosmoShopCsvPreflightReader;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CosmoShopCsvImportPipelineTest extends AbstractCosmoShopImportExportTestCase
{
    public function testItDryRunsAValidCosmoShopCsvThroughTheFullImportPipeline(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $progress = $this->dryRun($profileId, $this->csv());

        self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));
        self::assertSame(1, $progress->getProcessedRecords());
        self::assertNull($progress->getInvalidRecordsLogId());
    }

    public function testItSelectsTheCosmoShopPreflightReaderForTheProductProfile(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $path = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-reader-');
        self::assertNotFalse($path);
        file_put_contents($path, $this->csv());

        try {
            $service = static::getContainer()->get(ImportExportService::class);
            self::assertInstanceOf(ImportExportService::class, $service);
            $log = $service->prepareImport(
                $context,
                $profileId,
                new \DateTimeImmutable('+1 day'),
                new UploadedFile($path, 'products.csv', 'text/csv', null, true),
                [],
                true,
            );
        } finally {
            unlink($path);
        }

        $factory = static::getContainer()->get(ImportExportFactory::class);
        self::assertInstanceOf(ImportExportFactory::class, $factory);
        $import = $factory->create($log->getId(), 50, 50);
        $reader = (new \ReflectionProperty(ImportExport::class, 'reader'))->getValue($import);

        self::assertInstanceOf(CosmoShopCsvPreflightReader::class, $reader);
    }

    public function testItCreatesInvalidRecordsForMalformedCosmoShopData(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $progress = $this->dryRun($profileId, $this->csv(stock: '-1'));

        self::assertSame(Progress::STATE_FAILED, $progress->getState());
        self::assertSame(0, $progress->getProcessedRecords());
        self::assertNotNull($progress->getInvalidRecordsLogId());
        self::assertStringContainsString('CosmoShop stock must be a non-negative integer.', $this->importResult($progress));
    }

    public function testItCreatesInvalidRecordsForMissingRequiredEan(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $progress = $this->dryRun($profileId, $this->csv(ean: ''));

        self::assertSame(Progress::STATE_FAILED, $progress->getState());
        self::assertNotNull($progress->getInvalidRecordsLogId());
        self::assertStringContainsString('ean is set to required by the user but has no value', $this->importResult($progress));
    }

    public function testItCreatesAnInvalidRecordForAMalformedRowInTheMiddleOfTheCsv(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        [$header, $firstRow] = explode("\n", $this->csv(), 2);
        $malformedRow = 'MALFORMED-ROW;0;1;4260174423463;0;0;0;0;1;0;119.00';
        $csv = $header."\n".$firstRow."\n".$malformedRow."\n".str_replace('TEST-100034', 'LAST-ROW', $firstRow);

        $progress = $this->dryRun($profileId, $csv);

        self::assertSame(Progress::STATE_FAILED, $progress->getState());
        self::assertStringContainsString('CosmoShop CSV product row has 11 columns; expected 23.', $this->importResult($progress));
    }

    public function testItContinuesAfterAMalformedFirstProductRow(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        [$header, $validRow] = explode("\n", $this->csv(productNumber: 'VALID-AFTER-MALFORMED-001'), 2);
        $malformedRow = 'MALFORMED-FIRST;0;1;4260174423463;0;0;0;0;1;0;119.00';

        $progress = $this->dryRun($profileId, $header."\n".$malformedRow."\n".$validRow);

        self::assertSame(Progress::STATE_FAILED, $progress->getState());
        self::assertSame(1, $progress->getProcessedRecords());
        self::assertStringContainsString('CosmoShop CSV product row has 11 columns; expected 23.', $this->importResult($progress));
    }
}
