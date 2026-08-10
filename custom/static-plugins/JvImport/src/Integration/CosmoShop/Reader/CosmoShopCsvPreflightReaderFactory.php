<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Reader;

use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReaderFactory;

final class CosmoShopCsvPreflightReaderFactory extends AbstractReaderFactory
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CosmoShopPreflightFailureRegistry $failureRegistry,
        private readonly string $environment,
    ) {
    }

    public function create(ImportExportLogEntity $logEntity): AbstractReader
    {
        return new CosmoShopCsvPreflightReader(
            $this->logger,
            $this->failureRegistry,
            $logEntity->getId(),
            $logEntity->getProfile()->getTechnicalName(),
            $this->environment,
        );
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return 'text/csv' === $logEntity->getProfile()->getFileType()
            && null !== MarketImportProfile::marketForTechnicalName($logEntity->getProfile()->getTechnicalName());
    }
}
