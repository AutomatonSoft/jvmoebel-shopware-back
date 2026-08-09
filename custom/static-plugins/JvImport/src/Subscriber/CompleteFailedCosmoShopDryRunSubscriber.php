<?php declare(strict_types=1);

namespace Jv\Import\Subscriber;

use Doctrine\DBAL\Connection;
use Jv\Import\Integration\CosmoShop\Reader\CosmoShopPreflightFailureRegistry;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ConsoleEvents::ERROR)]
final readonly class CompleteFailedCosmoShopDryRunSubscriber
{
    public function __construct(
        private Connection $connection,
        private ImportExportService $importExportService,
        private CosmoShopPreflightFailureRegistry $failureRegistry,
    ) {
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        if ('import:entity' !== $event->getCommand()?->getName()) {
            return;
        }

        $importLogId = $this->failureRegistry->consumeFailedConsoleImportLogId();
        if (null === $importLogId) {
            return;
        }

        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->importExportService->saveProgress(new Progress($importLogId, Progress::STATE_FAILED));
    }
}
