<?php declare(strict_types=1);

namespace Jv\Import\Subscriber;

use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\Import\Integration\CosmoShop\Reader\CosmoShopPreflightFailureRegistry;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterProcessFinishedEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportExportHandlerEvent;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CosmoShopImportProcessLoggingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private ImportExportService $importExportService,
        private CosmoShopPreflightFailureRegistry $preflightFailureRegistry,
        private string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportAfterProcessFinishedEvent::class => 'logCompletedImport',
            ImportExportExceptionImportExportHandlerEvent::class => 'logUnhandledImportFailure',
        ];
    }

    public function logCompletedImport(ImportExportAfterProcessFinishedEvent $event): void
    {
        $log = $event->getLogEntity();
        if (!$this->isCosmoShopProductImport($log)) {
            return;
        }

        $progress = $event->getProgress();
        $this->logger->info('CosmoShop product import completed.', [
            ...$this->logContext($log),
            'state' => $progress->getState(),
            'records' => $progress->getProcessedRecords(),
            'invalidRecordsLogId' => $progress->getInvalidRecordsLogId(),
        ]);
    }

    public function logUnhandledImportFailure(ImportExportExceptionImportExportHandlerEvent $event): void
    {
        $exception = $event->getException();
        if (null === $exception) {
            return;
        }

        $importLogId = $event->getMessage()->getLogId();
        if ($this->preflightFailureRegistry->consumePreflightRejection($importLogId)) {
            return;
        }

        try {
            $log = $this->importExportService->findLog($event->getContext(), $importLogId);
        } catch (\Throwable) {
            return;
        }

        if (!$this->isCosmoShopProductImport($log)) {
            return;
        }

        $this->logger->error('CosmoShop product import failed.', [
            ...$this->logContext($log),
            'exception' => $exception,
        ]);
    }

    private function isCosmoShopProductImport(ImportExportLogEntity $log): bool
    {
        return $log->getActivity() === ImportExportLogEntity::ACTIVITY_IMPORT
            && null !== MarketImportProfile::marketForTechnicalName($log->getProfile()?->getTechnicalName());
    }

    /** @return array{operation: string, importLogId: string, profile: string, environment: string} */
    private function logContext(ImportExportLogEntity $log): array
    {
        return [
            'operation' => 'cosmoshop_product_import',
            'importLogId' => $log->getId(),
            'profile' => (string) $log->getProfile()?->getTechnicalName(),
            'environment' => $this->environment,
        ];
    }
}
