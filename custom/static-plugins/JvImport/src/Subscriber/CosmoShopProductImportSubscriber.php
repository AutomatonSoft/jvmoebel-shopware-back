<?php declare(strict_types=1);

namespace Jv\Import\Subscriber;

use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\Import\Service\ProductImport\Contract\ProductImportRecordPreparer;
use Jv\Import\Service\ProductMediaImport\PrepareCosmoShopProductMediaRecordService;
use Jv\Import\Service\ProductMediaImport\ValidateCosmoShopProductMediaCoverService;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CosmoShopProductImportSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ProductImportRecordPreparer $prepareRecord,
        private ValidateCosmoShopProductMediaCoverService $validateCover,
        private PrepareCosmoShopProductMediaRecordService $prepareMedia,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeImportRecordEvent::class => 'validateRecord',
        ];
    }

    public function validateRecord(ImportExportBeforeImportRecordEvent $event): void
    {
        $market = MarketImportProfile::marketForTechnicalName($event->getConfig()->get('profileName'));
        if (null === $market) {
            return;
        }
        $name = $event->getRow()['name'] ?? null;
        if (is_string($name) && str_starts_with($name, '__cosmoshop_csv_row_error__:')) {
            throw new \InvalidArgumentException(substr($name, strlen('__cosmoshop_csv_row_error__:')));
        }
        $this->validateCover->execute($event->getRow());

        $record = $this->prepareRecord->execute(
            $market,
            $event->getRow(),
            $event->getRecord(),
            $market->languageId(),
            $event->getContext(),
        );
        $event->setRecord($this->prepareMedia->execute($record, $event->getRow()));
    }
}
