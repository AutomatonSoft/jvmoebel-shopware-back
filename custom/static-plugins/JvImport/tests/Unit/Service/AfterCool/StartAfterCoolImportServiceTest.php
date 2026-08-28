<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\AfterCool;

use Jv\Import\Integration\AfterCool\AfterCoolApiClientInterface;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolFactory;
use Jv\Import\Message\AfterCoolImportPageMessage;
use Jv\Import\Service\AfterCool\Contract\AfterCoolImportRunStore;
use Jv\Import\Service\AfterCool\Exception\AfterCoolFactoryImportAlreadyRunningException;
use Jv\Import\Service\AfterCool\Exception\AfterCoolFactoryNotFoundException;
use Jv\Import\Service\AfterCool\StartAfterCoolImportService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class StartAfterCoolImportServiceTest extends TestCase
{
    public function testItCreatesAQueuedRunAndDispatchesOnlyTheFirstPageReference(): void
    {
        $context = Context::createDefaultContext();
        $runId = Uuid::randomHex();
        $api = $this->createMock(AfterCoolApiClientInterface::class);
        $api->expects(self::once())->method('getFactories')->willReturn([
            new AfterCoolFactory('504034', 'Factory A'),
            new AfterCoolFactory('504000', 'Factory B'),
        ]);
        $runs = $this->createMock(AfterCoolImportRunStore::class);
        $runs->expects(self::once())->method('createQueued')->with(
            '504034',
            'Factory A',
            'JV:lister:504034',
            $context,
        )->willReturn($runId);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (object $message): bool => $message instanceof AfterCoolImportPageMessage
                && $runId === $message->runId
                && 0 === $message->offset
                && ['runId', 'offset'] === array_keys(get_object_vars($message)),
        ))->willReturn(new Envelope(new \stdClass()));

        $createdRunId = $this->service($api, $runs, $messageBus)->start('504034', $context);

        self::assertSame($runId, $createdRunId);
    }

    public function testItRejectsAFactoryThatWasNotReturnedByAftercool(): void
    {
        $api = $this->createMock(AfterCoolApiClientInterface::class);
        $api->expects(self::once())->method('getFactories')->willReturn([new AfterCoolFactory('504034', 'Factory A')]);
        $runs = $this->createMock(AfterCoolImportRunStore::class);
        $runs->expects(self::never())->method('createQueued');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $this->expectException(AfterCoolFactoryNotFoundException::class);

        $this->service($api, $runs, $messageBus)->start('999999', Context::createDefaultContext());
    }

    public function testItDoesNotDispatchWhenTheFactoryAlreadyHasAnActiveRun(): void
    {
        $context = Context::createDefaultContext();
        $api = $this->createMock(AfterCoolApiClientInterface::class);
        $api->method('getFactories')->willReturn([new AfterCoolFactory('504034', 'Factory A')]);
        $runs = $this->createMock(AfterCoolImportRunStore::class);
        $runs->expects(self::once())->method('createQueued')->willThrowException(new AfterCoolFactoryImportAlreadyRunningException('504034'));
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $this->expectException(AfterCoolFactoryImportAlreadyRunningException::class);

        $this->service($api, $runs, $messageBus)->start('504034', $context);
    }

    public function testItReleasesThePersistentFactoryGuardWhenInitialDispatchFails(): void
    {
        $context = Context::createDefaultContext();
        $runId = Uuid::randomHex();
        $api = $this->createMock(AfterCoolApiClientInterface::class);
        $api->method('getFactories')->willReturn([new AfterCoolFactory('504034', 'Factory A')]);
        $runs = $this->createMock(AfterCoolImportRunStore::class);
        $runs->method('createQueued')->willReturn($runId);
        $runs->expects(self::once())->method('markFailed')->with(
            $runId,
            'messenger_dispatch_failed',
            self::logicalNot(self::stringContains('Redis connection details')),
            $context,
        );
        $messageBus = $this->createMock(MessageBusInterface::class);
        $failure = new \RuntimeException('Redis connection details must stay out of the run report.');
        $messageBus->expects(self::once())->method('dispatch')->willThrowException($failure);

        $this->expectExceptionObject($failure);

        $this->service($api, $runs, $messageBus)->start('504034', $context);
    }

    private function service(
        AfterCoolApiClientInterface $api,
        AfterCoolImportRunStore $runs,
        MessageBusInterface $messageBus,
    ): StartAfterCoolImportService {
        return new StartAfterCoolImportService(
            $api,
            $runs,
            $messageBus,
            new LockFactory(new InMemoryStore()),
        );
    }
}
