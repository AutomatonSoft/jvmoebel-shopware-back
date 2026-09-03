<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\MessageHandler;

use Jv\Import\Message\AfterCoolImportPageMessage;
use Jv\Import\MessageHandler\AfterCoolImportPageMessageHandler;
use Jv\Import\Service\AfterCool\Dto\AfterCoolPageProcessingResult;
use Jv\Import\Service\AfterCool\Import\ImportAfterCoolPageService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class AfterCoolImportPageMessageHandlerTest extends TestCase
{
    public function testItQueuesExactlyTheNextPageAfterTheCheckpointSucceeds(): void
    {
        $runId = Uuid::randomHex();
        $processor = $this->createMock(ImportAfterCoolPageService::class);
        $processor->expects(self::once())->method('process')->with(
            $runId,
            0,
            self::isInstanceOf(\Shopware\Core\Framework\Context::class),
        )->willReturn(AfterCoolPageProcessingResult::continueWith(100));
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (object $message): bool => $message instanceof AfterCoolImportPageMessage
                && $runId === $message->runId
                && 100 === $message->offset,
        ))->willReturn(new Envelope(new \stdClass()));

        (new AfterCoolImportPageMessageHandler($processor, $messageBus))(new AfterCoolImportPageMessage($runId, 0));
    }

    public function testItDoesNotQueueAnotherPageWhenHasMoreIsFalse(): void
    {
        $runId = Uuid::randomHex();
        $processor = $this->createMock(ImportAfterCoolPageService::class);
        $processor->expects(self::once())->method('process')->willReturn(AfterCoolPageProcessingResult::completed());
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        (new AfterCoolImportPageMessageHandler($processor, $messageBus))(new AfterCoolImportPageMessage($runId, 200));
    }

    public function testItDoesNotAdvanceTheQueueWhenPageProcessingFails(): void
    {
        $runId = Uuid::randomHex();
        $failure = new \RuntimeException('Temporary Aftercool failure.');
        $processor = $this->createMock(ImportAfterCoolPageService::class);
        $processor->expects(self::once())->method('process')->willThrowException($failure);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $this->expectExceptionObject($failure);

        (new AfterCoolImportPageMessageHandler($processor, $messageBus))(new AfterCoolImportPageMessage($runId, 0));
    }
}
