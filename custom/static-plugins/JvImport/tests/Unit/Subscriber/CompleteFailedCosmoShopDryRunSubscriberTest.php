<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Subscriber;

use Doctrine\DBAL\Connection;
use Jv\Import\Integration\CosmoShop\Reader\CosmoShopPreflightFailureRegistry;
use Jv\Import\Subscriber\CompleteFailedCosmoShopDryRunSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class CompleteFailedCosmoShopDryRunSubscriberTest extends TestCase
{
    public function testItCompletesARejectedCosmoShopDryRunAsFailed(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('rollBack');

        $importExportService = $this->createMock(ImportExportService::class);
        $importExportService
            ->expects(self::once())
            ->method('saveProgress')
            ->with(self::callback(static fn (Progress $progress): bool => '019fe6386ca771b29f5a8412a8cc3d95' === $progress->getLogId()
                && Progress::STATE_FAILED === $progress->getState()));

        $failureRegistry = new CosmoShopPreflightFailureRegistry();
        $failureRegistry->recordPreflightRejected('019fe6386ca771b29f5a8412a8cc3d95', true);

        $subscriber = new CompleteFailedCosmoShopDryRunSubscriber($connection, $importExportService, $failureRegistry);
        $event = new ConsoleErrorEvent(
            new ArrayInput([]),
            new BufferedOutput(),
            new \RuntimeException('Preflight failed.'),
            new Command('import:entity'),
        );

        $subscriber->onConsoleError($event);
    }
}
