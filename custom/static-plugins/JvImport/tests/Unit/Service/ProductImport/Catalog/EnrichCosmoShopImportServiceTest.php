<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\Catalog;

use Jv\Import\Integration\Csv\SemicolonCsvReader;
use Jv\Import\Integration\Okb\OkbProductApiClient;
use Jv\Import\Integration\Okb\OkbProductResponseNormalizer;
use Jv\Import\Integration\Okb\Profile\CatalogProductImportProfile;
use Jv\Import\Integration\Okb\Service\PrepareOkbProductMappingService;
use Jv\Import\Service\ProductImport\Catalog\EnrichCosmoShopImportService;
use Jv\Import\Service\ProductImport\Catalog\PrepareCatalogShopwareImportCsvService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class EnrichCosmoShopImportServiceTest extends TestCase
{
    private const string SOURCE_IMPORT_LOG_ID = '019fe6386ca771b29f5a8412a8cc3d95';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupEnrichmentDirectories(self::SOURCE_IMPORT_LOG_ID);
    }

    protected function tearDown(): void
    {
        $this->cleanupEnrichmentDirectories(self::SOURCE_IMPORT_LOG_ID);
        parent::tearDown();
    }

    public function testItBuildsAndQueuesAStandardCatalogImportForTheFinishedSourceImport(): void
    {
        $context = Context::createDefaultContext();
        $source = $this->sourceLog();
        $importExport = $this->createMock(ImportExportService::class);
        $importExport->expects(self::once())->method('findLog')->with($context, $source->getId())->willReturn($source);
        $importExport->expects(self::once())->method('prepareImport')->with(
            $context,
            CatalogProductImportProfile::definition()['id'],
            self::isInstanceOf(\DateTimeInterface::class),
            self::callback(function (UploadedFile $file): bool {
                $csv = file_get_contents($file->getPathname());

                return is_string($csv)
                    && str_contains($csv, 'parent;COSMO-1;4260454042902;14423;1951')
                    && str_contains($csv, 'child;COSMO-1;4260454042902;14423;1951');
            }),
            ['parameters' => ['jvCatalogEnrichmentSourceImportLogId' => $source->getId()]],
        )->willReturn($this->catalogLog());

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::once())->method('readStream')->with('source/import.csv')->willReturn($this->stream("product_number;ean\nCOSMO-1;4260454042902\n"));

        $profiles = $this->createMock(EntityRepository::class);
        $profiles->expects(self::once())->method('upsert')->with([CatalogProductImportProfile::definition()], $context);
        $catalogLogs = $this->catalogLogs();

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $message): bool => $message instanceof ImportExportMessage && '019fe6386ca771b29f5a8412a8cc3d96' === $message->getLogId()))->willReturn(new Envelope(new \stdClass()));

        $service = new EnrichCosmoShopImportService(
            $importExport,
            $filesystem,
            new SemicolonCsvReader(),
            new PrepareOkbProductMappingService(new SemicolonCsvReader(), $this->apiClient()),
            new PrepareCatalogShopwareImportCsvService(new SemicolonCsvReader()),
            $profiles,
            $catalogLogs,
            $messageBus,
            new LockFactory(new InMemoryStore()),
            (string) getcwd(),
        );
        $service->execute($source->getId(), $context);

        self::assertSame([], $this->enrichmentDirectories($source->getId()));
    }

    public function testItExcludesSourceRowsAlreadyReportedAsInvalid(): void
    {
        $context = Context::createDefaultContext();
        $source = $this->sourceLog();
        $source->setInvalidRecordsLogId('019fe6386ca771b29f5a8412a8cc3d97');
        $invalid = new ImportExportLogEntity();
        $invalid->setId('019fe6386ca771b29f5a8412a8cc3d97');
        $invalidFile = new ImportExportFileEntity();
        $invalidFile->setPath('source/invalid.csv');
        $invalid->setFile($invalidFile);
        $importExport = $this->createMock(ImportExportService::class);
        $importExport->expects(self::exactly(2))->method('findLog')->willReturnMap([
            [$context, $source->getId(), $source],
            [$context, $invalid->getId(), $invalid],
        ]);
        $importExport->expects(self::once())->method('prepareImport')->with(
            $context,
            CatalogProductImportProfile::definition()['id'],
            self::isInstanceOf(\DateTimeInterface::class),
            self::callback(function (UploadedFile $file): bool {
                $csv = file_get_contents($file->getPathname());

                return is_string($csv)
                    && str_contains($csv, 'COSMO-1')
                    && !str_contains($csv, 'COSMO-INVALID');
            }),
            ['parameters' => ['jvCatalogEnrichmentSourceImportLogId' => $source->getId()]],
        )->willReturn($this->catalogLog());
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::exactly(2))->method('readStream')->willReturnCallback(fn (string $path) => $this->stream(match ($path) {
            'source/import.csv' => "product_number;ean\nCOSMO-1;4260454042902\nCOSMO-INVALID;4260454043503\n",
            'source/invalid.csv' => "product_number;_error\nCOSMO-INVALID;invalid\n",
            default => throw new \LogicException(sprintf('Unexpected source file "%s".', $path)),
        }));
        $profiles = $this->createMock(EntityRepository::class);
        $profiles->expects(self::once())->method('upsert');
        $catalogLogs = $this->catalogLogs();
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        (new EnrichCosmoShopImportService(
            $importExport,
            $filesystem,
            new SemicolonCsvReader(),
            new PrepareOkbProductMappingService(new SemicolonCsvReader(), $this->apiClient()),
            new PrepareCatalogShopwareImportCsvService(new SemicolonCsvReader()),
            $profiles,
            $catalogLogs,
            $messageBus,
            new LockFactory(new InMemoryStore()),
            (string) getcwd(),
        ))->execute($source->getId(), $context);
    }

    public function testItDoesNotQueueAnotherCatalogImportForTheSameSourceImport(): void
    {
        $context = Context::createDefaultContext();
        $source = $this->sourceLog();
        $importExport = $this->createMock(ImportExportService::class);
        $importExport->expects(self::once())->method('findLog')->with($context, $source->getId())->willReturn($source);
        $importExport->expects(self::once())->method('prepareImport')->willReturn($this->catalogLog());
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::once())->method('readStream')->willReturn($this->stream("product_number;ean\nCOSMO-1;4260454042902\n"));
        $profiles = $this->createMock(EntityRepository::class);
        $profiles->expects(self::once())->method('upsert');
        $catalogLogs = $this->createMock(EntityRepository::class);
        $catalogLogSearches = 0;
        $catalogLogs->expects(self::exactly(2))->method('searchIds')->willReturnCallback(
            static function (Criteria $criteria, Context $queryContext) use (&$catalogLogSearches): IdSearchResult {
                ++$catalogLogSearches;

                return IdSearchResult::fromIds(
                    1 === $catalogLogSearches ? [] : ['019fe6386ca771b29f5a8412a8cc3d96'],
                    $criteria,
                    $queryContext,
                );
            },
        );
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $service = new EnrichCosmoShopImportService(
            $importExport,
            $filesystem,
            new SemicolonCsvReader(),
            new PrepareOkbProductMappingService(new SemicolonCsvReader(), $this->apiClient()),
            new PrepareCatalogShopwareImportCsvService(new SemicolonCsvReader()),
            $profiles,
            $catalogLogs,
            $messageBus,
            new LockFactory(new InMemoryStore()),
            (string) getcwd(),
        );
        $service->execute($source->getId(), $context);
        $service->execute($source->getId(), $context);
    }

    public function testItPreparesANewCatalogImportAfterTheFirstDispatchFails(): void
    {
        $context = Context::createDefaultContext();
        $source = $this->sourceLog();
        $importExport = $this->createMock(ImportExportService::class);
        $importExport->expects(self::exactly(2))->method('findLog')->with($context, $source->getId())->willReturn($source);
        $firstCatalogLog = $this->catalogLog();
        $secondCatalogLog = $this->catalogLog('019fe6386ca771b29f5a8412a8cc3d98');
        $importExport->expects(self::exactly(2))->method('prepareImport')->willReturnOnConsecutiveCalls($firstCatalogLog, $secondCatalogLog);
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::exactly(2))->method('readStream')->willReturnCallback(
            fn (): mixed => $this->stream("product_number;ean\nCOSMO-1;4260454042902\n"),
        );
        $profiles = $this->createMock(EntityRepository::class);
        $profiles->expects(self::exactly(2))->method('upsert');
        $catalogLogs = $this->createMock(EntityRepository::class);
        $searches = 0;
        $catalogLogs->expects(self::exactly(2))->method('searchIds')->willReturnCallback(
            static function (Criteria $criteria, Context $queryContext) use (&$searches): IdSearchResult {
                ++$searches;

                return IdSearchResult::fromIds([], $criteria, $queryContext);
            },
        );
        $catalogLogs->expects(self::once())->method('delete')->with([['id' => $firstCatalogLog->getId()]], $context);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::exactly(2))->method('dispatch')->willReturnOnConsecutiveCalls(
            self::throwException(new \RuntimeException('Redis is unavailable.')),
            new Envelope(new \stdClass()),
        );
        $service = new EnrichCosmoShopImportService($importExport, $filesystem, new SemicolonCsvReader(), new PrepareOkbProductMappingService(new SemicolonCsvReader(), $this->apiClient(2)), new PrepareCatalogShopwareImportCsvService(new SemicolonCsvReader()), $profiles, $catalogLogs, $messageBus, new LockFactory(new InMemoryStore()), (string) getcwd());

        try {
            $service->execute($source->getId(), $context);
            self::fail('The first dispatch must fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Redis is unavailable.', $exception->getMessage());
        }
        $service->execute($source->getId(), $context);
    }

    public function testItDoesNotRequeueAnExistingCatalogImportForTheSameSourceImport(): void
    {
        $context = Context::createDefaultContext();
        $source = $this->sourceLog();
        $importExport = $this->createMock(ImportExportService::class);
        $importExport->expects(self::never())->method('findLog');
        $importExport->expects(self::never())->method('prepareImport');
        $catalogLogs = $this->createMock(EntityRepository::class);
        $catalogLogs->expects(self::once())->method('searchIds')->willReturnCallback(
            static fn (Criteria $criteria, Context $queryContext): IdSearchResult => IdSearchResult::fromIds(['019fe6386ca771b29f5a8412a8cc3d96'], $criteria, $queryContext),
        );
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        (new EnrichCosmoShopImportService(
            $importExport,
            $this->createMock(FilesystemOperator::class),
            new SemicolonCsvReader(),
            new PrepareOkbProductMappingService(new SemicolonCsvReader(), $this->apiClient(0)),
            new PrepareCatalogShopwareImportCsvService(new SemicolonCsvReader()),
            $this->createMock(EntityRepository::class),
            $catalogLogs,
            $messageBus,
            new LockFactory(new InMemoryStore()),
            (string) getcwd(),
        ))->execute($source->getId(), $context);
    }

    public function testItDoesNotStartASecondConsumerWhileTheSourceImportIsLocked(): void
    {
        $context = Context::createDefaultContext();
        $source = $this->sourceLog();
        $locks = new LockFactory(new InMemoryStore());
        $firstConsumerLock = $locks->createLock('jv_catalog_enrichment_'.$source->getId(), 7200.0);
        self::assertTrue($firstConsumerLock->acquire());
        $importExport = $this->createMock(ImportExportService::class);
        $importExport->expects(self::never())->method('findLog');
        $logs = $this->createMock(EntityRepository::class);
        $logs->expects(self::never())->method('searchIds');
        $service = new EnrichCosmoShopImportService($importExport, $this->createMock(FilesystemOperator::class), new SemicolonCsvReader(), new PrepareOkbProductMappingService(new SemicolonCsvReader(), $this->apiClient(0)), new PrepareCatalogShopwareImportCsvService(new SemicolonCsvReader()), $this->createMock(EntityRepository::class), $logs, $this->createMock(MessageBusInterface::class), $locks, (string) getcwd());

        try {
            $service->execute($source->getId(), $context);
        } finally {
            $firstConsumerLock->release();
        }
    }

    /** @return EntityRepository<\Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogCollection> */
    private function catalogLogs(): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('searchIds')->willReturnCallback(
            static fn (Criteria $criteria, Context $queryContext): IdSearchResult => IdSearchResult::fromIds([], $criteria, $queryContext),
        );

        return $repository;
    }

    private function apiClient(int $requests = 1): OkbProductApiClient
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->with(false)->willReturn(['productVariations' => [[
            'sku' => '4260454042902',
            'ean' => '4260454042902',
            'productDescription' => ['category' => '3D-Brille', 'attributes' => []],
        ]]]);
        $client = $this->createMock(HttpClientInterface::class);
        if (0 < $requests) {
            $client->expects(self::exactly($requests))->method('request')->willReturn($response);
        }

        return new OkbProductApiClient($client, new OkbProductResponseNormalizer(), 'https://okb.example');
    }

    private function sourceLog(): ImportExportLogEntity
    {
        $profile = new ImportExportProfileEntity();
        $profile->setTechnicalName('jv_cosmoshop_product_jvmoebel_de');
        $file = new ImportExportFileEntity();
        $file->setPath('source/import.csv');
        $log = new ImportExportLogEntity();
        $log->setId(self::SOURCE_IMPORT_LOG_ID);
        $log->setActivity(ImportExportLogEntity::ACTIVITY_IMPORT);
        $log->setProfile($profile);
        $log->setFile($file);

        return $log;
    }

    private function catalogLog(string $id = '019fe6386ca771b29f5a8412a8cc3d96'): ImportExportLogEntity
    {
        $log = new ImportExportLogEntity();
        $log->setId($id);
        $log->setActivity(ImportExportLogEntity::ACTIVITY_IMPORT);

        return $log;
    }

    /** @return resource */
    private function stream(string $contents)
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /** @return list<string> */
    private function enrichmentDirectories(string $sourceImportLogId): array
    {
        $matches = glob($this->enrichmentDirectoryPattern($sourceImportLogId));

        return false === $matches ? [] : $matches;
    }

    private function enrichmentDirectoryPattern(string $sourceImportLogId): string
    {
        return (string) getcwd().'/var/import/okb-enrichment/'.$sourceImportLogId.'-*';
    }

    private function cleanupEnrichmentDirectories(string $sourceImportLogId): void
    {
        foreach ($this->enrichmentDirectories($sourceImportLogId) as $directory) {
            $this->removeDirectory($directory);
        }
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
