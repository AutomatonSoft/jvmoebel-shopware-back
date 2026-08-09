<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Subscriber;

use Jv\Import\Integration\CosmoShop\Reader\CosmoShopPreflightFailureRegistry;
use Jv\Import\Subscriber\CosmoShopImportProcessLoggingSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterProcessFinishedEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportExportHandlerEvent;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;

final class CosmoShopImportProcessLoggingSubscriberTest extends TestCase
{
    public function testItLogsTheCompletedCosmoShopProductImport(): void
    {
        $log = $this->cosmoShopImportLog();
        $progress = new Progress($log->getId(), Progress::STATE_SUCCEEDED);
        $progress->addProcessedRecords(20);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with('CosmoShop product import completed.', [
                'operation' => 'cosmoshop_product_import',
                'importLogId' => $log->getId(),
                'profile' => 'jv_cosmoshop_product_jvmoebel_de',
                'environment' => 'test',
                'state' => Progress::STATE_SUCCEEDED,
                'records' => 20,
                'invalidRecordsLogId' => null,
            ]);

        $subscriber = new CosmoShopImportProcessLoggingSubscriber(
            $logger,
            $this->createStub(ImportExportService::class),
            new CosmoShopPreflightFailureRegistry(),
            'test',
        );

        $subscriber->logCompletedImport(new ImportExportAfterProcessFinishedEvent(Context::createDefaultContext(), $log, $progress));
    }

    public function testItLogsAnUnhandledCosmoShopProductImportFailure(): void
    {
        $log = $this->cosmoShopImportLog();
        $importExportService = $this->createMock(ImportExportService::class);
        $importExportService
            ->expects(self::once())
            ->method('findLog')
            ->willReturn($log);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'CosmoShop product import failed.',
                self::callback(static fn (array $context): bool => $context['importLogId'] === $log->getId()
                    && $context['profile'] === 'jv_cosmoshop_product_jvmoebel_de'
                    && $context['exception'] instanceof \RuntimeException),
            );

        $subscriber = new CosmoShopImportProcessLoggingSubscriber(
            $logger,
            $importExportService,
            new CosmoShopPreflightFailureRegistry(),
            'test',
        );
        $context = Context::createDefaultContext();
        $event = new ImportExportExceptionImportExportHandlerEvent(
            new \RuntimeException('Network failure.'),
            new ImportExportMessage($context, $log->getId(), ImportExportLogEntity::ACTIVITY_IMPORT),
            $context,
        );

        $subscriber->logUnhandledImportFailure($event);
    }

    private function cosmoShopImportLog(): ImportExportLogEntity
    {
        $profile = new ImportExportProfileEntity();
        $profile->setTechnicalName('jv_cosmoshop_product_jvmoebel_de');

        $log = new ImportExportLogEntity();
        $log->setId('019fe6386ca771b29f5a8412a8cc3d95');
        $log->setActivity(ImportExportLogEntity::ACTIVITY_IMPORT);
        $log->setProfile($profile);

        return $log;
    }
}
