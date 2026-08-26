<?php declare(strict_types=1);

namespace Jv\Import\Subscriber;

use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\Import\Message\CosmoShopCatalogEnrichmentMessage;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterProcessFinishedEvent;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class QueueCosmoShopCatalogEnrichmentSubscriber implements EventSubscriberInterface
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ImportExportAfterProcessFinishedEvent::class => 'queue'];
    }

    public function queue(ImportExportAfterProcessFinishedEvent $event): void
    {
        $log = $event->getLogEntity();
        if (
            !in_array($event->getProgress()->getState(), [Progress::STATE_SUCCEEDED, Progress::STATE_FAILED], true)
            || (Progress::STATE_FAILED === $event->getProgress()->getState() && 0 === $event->getProgress()->getProcessedRecords())
            || ImportExportLogEntity::ACTIVITY_IMPORT !== $log->getActivity()
            || null === MarketImportProfile::marketForTechnicalName($log->getProfile()?->getTechnicalName())
        ) {
            return;
        }

        $this->messageBus->dispatch(new CosmoShopCatalogEnrichmentMessage($log->getId()));
    }
}
