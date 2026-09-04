<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Subscriber;

use Jv\Import\Message\CosmoShopCatalogEnrichmentMessage;
use Jv\Import\Subscriber\QueueCosmoShopCatalogEnrichmentSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class QueueCosmoShopCatalogEnrichmentSubscriberTest extends TestCase
{
    public function testItQueuesEnrichmentForASuccessfulCosmoShopProductImport(): void
    {
        $log = $this->log('jv_cosmoshop_product_jvmoebel_de');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $message): bool => $message instanceof CosmoShopCatalogEnrichmentMessage && $log->getId() === $message->sourceImportLogId))->willReturn(new Envelope(new \stdClass()));
        $importExport = $this->importExport($log);

        (new QueueCosmoShopCatalogEnrichmentSubscriber($messageBus, $importExport))->queue($this->written($log->getId(), Progress::STATE_SUCCEEDED));
    }

    public function testItIgnoresProgressFailedWithoutRecordsAndUnrelatedImports(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $failed = $this->log('jv_cosmoshop_product_jvmoebel_de');
        $other = $this->log('default_product');
        $subscriber = new QueueCosmoShopCatalogEnrichmentSubscriber($messageBus, $this->importExport($failed, $other));
        $subscriber->queue($this->written($failed->getId(), Progress::STATE_PROGRESS));
        $subscriber->queue($this->written($failed->getId(), Progress::STATE_FAILED));
        $subscriber->queue($this->written($other->getId(), Progress::STATE_SUCCEEDED));
    }

    public function testItQueuesTheProcessedRecordsWhenOneSourceRowIsInvalid(): void
    {
        $log = $this->log('jv_cosmoshop_product_jvmoebel_de');
        $log->setRecords(99);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $message): bool => $message instanceof CosmoShopCatalogEnrichmentMessage && $log->getId() === $message->sourceImportLogId))->willReturn(new Envelope(new \stdClass()));

        (new QueueCosmoShopCatalogEnrichmentSubscriber($messageBus, $this->importExport($log)))->queue($this->written($log->getId(), Progress::STATE_FAILED));
    }

    private function importExport(ImportExportLogEntity ...$logs): ImportExportService
    {
        $logsById = [];
        foreach ($logs as $log) {
            $logsById[$log->getId()] = $log;
        }

        $service = $this->createMock(ImportExportService::class);
        $service->method('findLog')->willReturnCallback(static fn (Context $context, string $id): ImportExportLogEntity => $logsById[$id]);

        return $service;
    }

    private function written(string $logId, string $state): EntityWrittenEvent
    {
        return new EntityWrittenEvent(
            'import_export_log',
            [new EntityWriteResult($logId, ['state' => $state], 'import_export_log', EntityWriteResult::OPERATION_UPDATE)],
            Context::createDefaultContext(),
        );
    }

    private function log(string $technicalName): ImportExportLogEntity
    {
        $profile = new ImportExportProfileEntity();
        $profile->setTechnicalName($technicalName);
        $log = new ImportExportLogEntity();
        $log->setId('019fe6386ca771b29f5a8412a8cc3d95');
        $log->setActivity(ImportExportLogEntity::ACTIVITY_IMPORT);
        $log->setProfile($profile);

        return $log;
    }
}
