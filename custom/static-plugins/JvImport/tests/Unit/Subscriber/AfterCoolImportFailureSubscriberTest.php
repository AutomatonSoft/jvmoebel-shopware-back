<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Subscriber;

use Jv\Import\Message\AfterCoolImportPageMessage;
use Jv\Import\Service\AfterCool\Persistence\AfterCoolImportRunStore;
use Jv\Import\Subscriber\AfterCoolImportFailureSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class AfterCoolImportFailureSubscriberTest extends TestCase
{
    public function testTerminalFailureLogContainsOnlySafeMetadata(): void
    {
        $runId = Uuid::randomHex();
        $runs = $this->createMock(AfterCoolImportRunStore::class);
        $runs->expects(self::once())->method('markFailed')->with(
            $runId,
            'aftercool_import_failed',
            'The Aftercool import could not be completed.',
            self::anything(),
        );
        $logger = new class extends AbstractLogger {
            /** @var list<array{message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['message' => (string) $message, 'context' => $context];
            }
        };
        $secret = 'raw-response-containing-api-password';
        $failure = new UnrecoverableMessageHandlingException(
            'Permanent Aftercool failure.',
            previous: new \RuntimeException($secret),
        );
        $event = new WorkerMessageFailedEvent(
            new Envelope(new AfterCoolImportPageMessage($runId, 0)),
            'async',
            $failure,
        );

        (new AfterCoolImportFailureSubscriber($runs, $logger))->onFailed($event);

        self::assertCount(1, $logger->records);
        self::assertSame($runId, $logger->records[0]['context']['runId']);
        self::assertSame(0, $logger->records[0]['context']['offset']);
        self::assertArrayNotHasKey('exception', $logger->records[0]['context']);
        self::assertStringNotContainsString($secret, serialize($logger->records));
    }
}
