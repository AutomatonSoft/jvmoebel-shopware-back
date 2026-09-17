<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Jv\Import\Integration\Okb\Profile\CatalogProductImportProfile;
use Jv\Import\Integration\Okb\Service\PrepareOkbProductMappingService;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogCollection;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class QueueCatalogEnrichmentImportService
{
    /**
     * @param EntityRepository<EntityCollection<ImportExportProfileEntity>> $profileRepository
     * @param EntityRepository<ImportExportLogCollection>                   $logRepository
     */
    public function __construct(
        private PrepareOkbProductMappingService $mappingService,
        private PrepareCatalogShopwareImportCsvService $csvService,
        private ImportExportService $importExportService,
        private EntityRepository $profileRepository,
        private EntityRepository $logRepository,
        private MessageBusInterface $messageBus,
        private string $projectDir,
    ) {
    }

    public function execute(string $preparedCsv, string $workingDirectory, CatalogEnrichmentSource $source, string $sourceKey, \Closure $refreshLock, Context $context): void
    {
        $mappingDirectory = $workingDirectory.'/mapping';
        if (!mkdir($mappingDirectory, 0775) && !is_dir($mappingDirectory)) {
            throw new \RuntimeException(sprintf('Could not create OKB mapping directory "%s".', $mappingDirectory));
        }
        $this->mappingService->execute($preparedCsv, $this->projectDir.'/data/import/okb', $mappingDirectory, null, $refreshLock);
        $catalogCsv = $workingDirectory.'/catalog-products.csv';
        $this->csvService->execute(
            $mappingDirectory.'/okb-product-mapping.csv',
            $mappingDirectory.'/okb-product-attributes.csv',
            $catalogCsv,
            $mappingDirectory.'/okb-product-mapping-failures.csv',
            $source->activatesParents(),
        );
        $this->profileRepository->upsert([CatalogProductImportProfile::definition()], $context);
        $catalogLog = $this->importExportService->prepareImport(
            $context,
            CatalogProductImportProfile::definition()['id'],
            new \DateTimeImmutable('+1 day'),
            new UploadedFile($catalogCsv, 'okb-catalog-products.csv', 'text/csv', null, true),
            ['parameters' => [$source->value => $sourceKey]],
        );
        try {
            $this->messageBus->dispatch(new ImportExportMessage($context, $catalogLog->getId(), $catalogLog->getActivity()));
        } catch (\Throwable $exception) {
            $this->logRepository->delete([['id' => $catalogLog->getId()]], $context);

            throw $exception;
        }
    }

    public function isQueued(CatalogEnrichmentSource $source, string $sourceKey, Context $context): bool
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('profileId', CatalogProductImportProfile::definition()['id']))
            ->addFilter(new EqualsFilter('config.parameters.'.$source->value, $sourceKey))
            ->setLimit(1);

        return null !== $this->logRepository->searchIds($criteria, $context)->firstId();
    }
}
