<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Subscriber;

use Jv\Import\Message\CosmoShopCatalogEnrichmentMessage;
use Jv\Import\Subscriber\QueueCosmoShopCatalogEnrichmentSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterProcessFinishedEvent;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class QueueCosmoShopCatalogEnrichmentSubscriberTest extends TestCase
{
    public function testItQueuesEnrichmentForASuccessfulCosmoShopProductImport(): void
    {
        $log = $this->log('jv_cosmoshop_product_jvmoebel_de');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->with(
            self::callback(static fn (object $message): bool => $message instanceof CosmoShopCatalogEnrichmentMessage && $log->getId() === $message->sourceImportLogId),
            self::callback(static fn (array $stamps): bool => 1 === count($stamps) && $stamps[0] instanceof TransportNamesStamp && ['low_priority'] === $stamps[0]->getTransportNames()),
        )->willReturn(new Envelope(new \stdClass()));

        (new QueueCosmoShopCatalogEnrichmentSubscriber($messageBus))->queue(new ImportExportAfterProcessFinishedEvent(Context::createDefaultContext(), $log, new Progress($log->getId(), Progress::STATE_SUCCEEDED)));
    }

    public function testItIgnoresFailedAndUnrelatedImports(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');
        $subscriber = new QueueCosmoShopCatalogEnrichmentSubscriber($messageBus);

        $failed = $this->log('jv_cosmoshop_product_jvmoebel_de');
        $subscriber->queue(new ImportExportAfterProcessFinishedEvent(Context::createDefaultContext(), $failed, new Progress($failed->getId(), Progress::STATE_FAILED)));
        $other = $this->log('default_product');
        $subscriber->queue(new ImportExportAfterProcessFinishedEvent(Context::createDefaultContext(), $other, new Progress($other->getId(), Progress::STATE_SUCCEEDED)));
    }

    public function testItQueuesTheProcessedRecordsWhenOneSourceRowIsInvalid(): void
    {
        $log = $this->log('jv_cosmoshop_product_jvmoebel_de');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->with(
            self::callback(static fn (object $message): bool => $message instanceof CosmoShopCatalogEnrichmentMessage && $log->getId() === $message->sourceImportLogId),
            self::callback(static fn (array $stamps): bool => 1 === count($stamps) && $stamps[0] instanceof TransportNamesStamp && ['low_priority'] === $stamps[0]->getTransportNames()),
        )->willReturn(new Envelope(new \stdClass()));
        $progress = new Progress($log->getId(), Progress::STATE_FAILED);
        $progress->addProcessedRecords(99);

        (new QueueCosmoShopCatalogEnrichmentSubscriber($messageBus))->queue(new ImportExportAfterProcessFinishedEvent(Context::createDefaultContext(), $log, $progress));
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
