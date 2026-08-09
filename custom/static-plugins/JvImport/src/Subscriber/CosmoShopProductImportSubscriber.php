<?php declare(strict_types=1);

namespace Jv\CatalogImport\Subscriber;

use Jv\CatalogImport\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\CatalogImport\Service\ProductImport\Contract\ProductImportRecordPreparer;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CosmoShopProductImportSubscriber implements EventSubscriberInterface
{
    public function __construct(private ProductImportRecordPreparer $prepareRecord)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ImportExportBeforeImportRecordEvent::class => 'validateRecord'];
    }

    public function validateRecord(ImportExportBeforeImportRecordEvent $event): void
    {
        $market = MarketImportProfile::marketForTechnicalName($event->getConfig()->get('profileName'));
        if (null === $market) {
            return;
        }

        $event->setRecord($this->prepareRecord->execute(
            $market,
            $event->getRow(),
            $event->getRecord(),
            $event->getContext()->getLanguageId(),
        ));
    }
}
