<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Subscriber;

use Jv\Import\Message\CosmoShopProductRedirectImportMessage;
use Jv\Import\Subscriber\QueueCosmoShopProductRedirectImportSubscriber;
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

final class QueueCosmoShopProductRedirectImportSubscriberTest extends TestCase
{
    public function testItQueuesProductRedirectImportAfterSuccessfulRealProductImport(): void
    {
        $log = $this->log('jv_cosmoshop_product_jvmoebel_de');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (object $message): bool => $message instanceof CosmoShopProductRedirectImportMessage
                && $message->sourceImportLogId === $log->getId(),
        ))->willReturn(new Envelope(new \stdClass()));

        (new QueueCosmoShopProductRedirectImportSubscriber($messageBus, $this->importExport($log)))
            ->queue($this->written($log->getId(), Progress::STATE_SUCCEEDED));
    }

    public function testItQueuesPartialImportOnlyWhenAtLeastOneProductWasProcessed(): void
    {
        $withoutRecords = $this->log('jv_cosmoshop_product_jvmoebel_de');
        $withRecords = $this->log('jv_cosmoshop_product_jvmoebel_de', '019fe6386ca771b29f5a8412a8cc3d96');
        $withRecords->setRecords(3);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (object $message): bool => $message instanceof CosmoShopProductRedirectImportMessage
                && $message->sourceImportLogId === $withRecords->getId(),
        ))->willReturn(new Envelope(new \stdClass()));
        $subscriber = new QueueCosmoShopProductRedirectImportSubscriber($messageBus, $this->importExport($withoutRecords, $withRecords));

        $subscriber->queue($this->written($withoutRecords->getId(), Progress::STATE_FAILED));
        $subscriber->queue($this->written($withRecords->getId(), Progress::STATE_FAILED));
    }

    public function testItIgnoresExportDryRunProgressAndUnrelatedProfiles(): void
    {
        $export = $this->log('jv_cosmoshop_product_jvmoebel_de');
        $export->setActivity(ImportExportLogEntity::ACTIVITY_EXPORT);
        $dryRun = $this->log('jv_cosmoshop_product_jvmoebel_de', '019fe6386ca771b29f5a8412a8cc3d96');
        $dryRun->setActivity(ImportExportLogEntity::ACTIVITY_DRYRUN);
        $other = $this->log('default_product', '019fe6386ca771b29f5a8412a8cc3d97');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');
        $subscriber = new QueueCosmoShopProductRedirectImportSubscriber($messageBus, $this->importExport($export, $dryRun, $other));

        $subscriber->queue($this->written($export->getId(), Progress::STATE_SUCCEEDED));
        $subscriber->queue($this->written($dryRun->getId(), Progress::STATE_SUCCEEDED));
        $subscriber->queue($this->written($other->getId(), Progress::STATE_SUCCEEDED));
        $subscriber->queue($this->written($other->getId(), Progress::STATE_PROGRESS));
    }

    private function importExport(ImportExportLogEntity ...$logs): ImportExportService
    {
        $logsById = [];
        foreach ($logs as $log) {
            $logsById[$log->getId()] = $log;
        }

        $service = $this->createMock(ImportExportService::class);
        $service->method('findLog')->willReturnCallback(
            static fn (Context $context, string $id): ImportExportLogEntity => $logsById[$id],
        );

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

    private function log(string $technicalName, string $id = '019fe6386ca771b29f5a8412a8cc3d95'): ImportExportLogEntity
    {
        $profile = new ImportExportProfileEntity();
        $profile->setTechnicalName($technicalName);
        $log = new ImportExportLogEntity();
        $log->setId($id);
        $log->setActivity(ImportExportLogEntity::ACTIVITY_IMPORT);
        $log->setProfile($profile);

        return $log;
    }
}
