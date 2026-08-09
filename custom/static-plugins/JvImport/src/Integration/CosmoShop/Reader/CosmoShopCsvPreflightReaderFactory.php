<?php declare(strict_types=1);

namespace Jv\CatalogImport\Integration\CosmoShop\Reader;

use Jv\CatalogImport\Integration\CosmoShop\Profile\MarketImportProfile;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReaderFactory;

final class CosmoShopCsvPreflightReaderFactory extends AbstractReaderFactory
{
    public function create(ImportExportLogEntity $logEntity): AbstractReader
    {
        return new CosmoShopCsvPreflightReader();
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return 'text/csv' === $logEntity->getProfile()->getFileType()
            && null !== MarketImportProfile::marketForTechnicalName($logEntity->getProfile()->getTechnicalName());
    }
}
