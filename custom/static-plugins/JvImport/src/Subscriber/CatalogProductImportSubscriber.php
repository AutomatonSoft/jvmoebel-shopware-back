<?php declare(strict_types=1);

namespace Jv\Import\Subscriber;

use Jv\Import\Integration\Okb\Profile\CatalogProductImportProfile;
use Jv\Import\Service\ProductImport\Catalog\PrepareCatalogShopwareProductImportRecordService;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CatalogProductImportSubscriber implements EventSubscriberInterface
{
    public function __construct(private PrepareCatalogShopwareProductImportRecordService $preparer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ImportExportBeforeImportRecordEvent::class => 'prepare'];
    }

    public function prepare(ImportExportBeforeImportRecordEvent $event): void
    {
        if (CatalogProductImportProfile::TECHNICAL_NAME !== $event->getConfig()->get('profileName')) {
            return;
        }
        $event->setRecord($this->preparer->execute($event->getRow(), $event->getContext()));
    }
}
