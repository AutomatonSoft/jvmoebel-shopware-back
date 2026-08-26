<?php declare(strict_types=1);

namespace Jv\Import\Subscriber;

use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\Import\Message\CosmoShopCatalogEnrichmentMessage;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEvents;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class QueueCosmoShopCatalogEnrichmentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private ImportExportService $importExportService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [ImportExportLogEvents::IMPORT_EXPORT_LOG_WRITTEN_EVENT => 'queue'];
    }

    public function queue(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $state = $result->getProperty('state');
            if (!in_array($state, [Progress::STATE_SUCCEEDED, Progress::STATE_FAILED], true)) {
                continue;
            }

            $log = $this->importExportService->findLog($event->getContext(), $result->getPrimaryKey());
            if (
                ImportExportLogEntity::ACTIVITY_IMPORT !== $log->getActivity()
                || null === MarketImportProfile::marketForTechnicalName($log->getProfile()?->getTechnicalName())
                || (Progress::STATE_FAILED === $state && 0 === $log->getRecords())
            ) {
                continue;
            }

            $this->messageBus->dispatch(new CosmoShopCatalogEnrichmentMessage($log->getId()));
        }
    }
}
