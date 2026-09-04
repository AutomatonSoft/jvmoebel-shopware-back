<?php declare(strict_types=1);

namespace Jv\Import\Subscriber;

use Jv\Import\Integration\Okb\Profile\CatalogProductImportProfile;
use Jv\Import\Service\ProductImport\Catalog\PrepareCatalogShopwareProductImportRecordService;
use Jv\Import\Service\ProductImport\Catalog\ReconcileCatalogProductImportRecordService;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CatalogProductImportSubscriber implements EventSubscriberInterface
{
    public function __construct(private PrepareCatalogShopwareProductImportRecordService $preparer, private ReconcileCatalogProductImportRecordService $reconciler)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ImportExportBeforeImportRecordEvent::class => 'prepare', ImportExportAfterImportRecordEvent::class => 'reconcile'];
    }

    public function prepare(ImportExportBeforeImportRecordEvent $event): void
    {
        if (CatalogProductImportProfile::TECHNICAL_NAME !== $event->getConfig()->get('profileName')) {
            return;
        }
        $record = $this->preparer->execute($event->getRow(), $event->getContext());
        $event->setRecord($record);
    }

    public function reconcile(ImportExportAfterImportRecordEvent $event): void
    {
        if (CatalogProductImportProfile::TECHNICAL_NAME !== $event->getConfig()->get('profileName')) {
            return;
        }
        $this->preparer->markPersistedOptions($event->getRecord());
        $type = $event->getRow()['record_type'] ?? '';
        $this->reconciler->execute($event->getRecord(), is_string($type) ? $type : '', $event->getContext());
    }
}
