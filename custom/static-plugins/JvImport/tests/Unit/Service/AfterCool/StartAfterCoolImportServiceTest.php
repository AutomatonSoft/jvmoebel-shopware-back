<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\AfterCool;

use Jv\Import\Message\AfterCoolImportPageMessage;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolFactory;
use Jv\Import\Service\AfterCool\Exception\AfterCoolFactoryImportAlreadyRunningException;
use Jv\Import\Service\AfterCool\Exception\AfterCoolFactoryNotFoundException;
use Jv\Import\Service\AfterCool\Import\StartAfterCoolImportService;
use Jv\Import\Service\AfterCool\Persistence\AfterCoolImportRunStore;
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
        $source = $this->createMock(AfterCoolProductSourceInterface::class);
        $source->expects(self::once())->method('getFactories')->willReturn([
            new AfterCoolFactory(504034, 'Factory A'),
            new AfterCoolFactory(504000, 'Factory B'),
        ]);
        $runs = $this->createMock(AfterCoolImportRunStore::class);
        $runs->expects(self::once())->method('createQueued')->with(
            504034,
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

        $createdRunId = $this->service($source, $runs, $messageBus)->start(504034, $context);

        self::assertSame($runId, $createdRunId);
    }

    public function testItRejectsAFactoryThatWasNotReturnedByAftercool(): void
    {
        $source = $this->createMock(AfterCoolProductSourceInterface::class);
        $source->expects(self::once())->method('getFactories')->willReturn([new AfterCoolFactory(504034, 'Factory A')]);
        $runs = $this->createMock(AfterCoolImportRunStore::class);
        $runs->expects(self::never())->method('createQueued');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $this->expectException(AfterCoolFactoryNotFoundException::class);

        $this->service($source, $runs, $messageBus)->start(999999, Context::createDefaultContext());
    }

    public function testItDoesNotDispatchWhenTheFactoryAlreadyHasAnActiveRun(): void
    {
        $context = Context::createDefaultContext();
        $source = $this->createMock(AfterCoolProductSourceInterface::class);
        $source->method('getFactories')->willReturn([new AfterCoolFactory(504034, 'Factory A')]);
        $runs = $this->createMock(AfterCoolImportRunStore::class);
        $runs->expects(self::once())->method('createQueued')->willThrowException(new AfterCoolFactoryImportAlreadyRunningException(504034));
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $this->expectException(AfterCoolFactoryImportAlreadyRunningException::class);

        $this->service($source, $runs, $messageBus)->start(504034, $context);
    }

    public function testItReleasesThePersistentFactoryGuardWhenInitialDispatchFails(): void
    {
        $context = Context::createDefaultContext();
        $runId = Uuid::randomHex();
        $source = $this->createMock(AfterCoolProductSourceInterface::class);
        $source->method('getFactories')->willReturn([new AfterCoolFactory(504034, 'Factory A')]);
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

        $this->service($source, $runs, $messageBus)->start(504034, $context);
    }

    private function service(
        AfterCoolProductSourceInterface $source,
        AfterCoolImportRunStore $runs,
        MessageBusInterface $messageBus,
    ): StartAfterCoolImportService {
        return new StartAfterCoolImportService(
            $source,
            $runs,
            $messageBus,
            new LockFactory(new InMemoryStore()),
        );
    }
}
