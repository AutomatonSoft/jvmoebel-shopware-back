<?php declare(strict_types=1);

namespace Jv\Import\Subscriber;

use Jv\Import\Integration\AfterCool\Exception\AfterCoolApiException;
use Jv\Import\Message\AfterCoolImportPageMessage;
use Jv\Import\Service\AfterCool\Persistence\AfterCoolImportRunStore;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final readonly class AfterCoolImportFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(private AfterCoolImportRunStore $runs, private LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkerMessageFailedEvent::class => 'onFailed'];
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry() || !$event->getEnvelope()->getMessage() instanceof AfterCoolImportPageMessage) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        $exception = $event->getThrowable();
        $safeCode = $this->safeCode($exception);
        $this->logger->error('Aftercool import run failed after Messenger retries.', [
            'runId' => $message->runId,
            'offset' => $message->offset,
            'safeCode' => $safeCode,
            'exceptionClass' => $exception::class,
        ]);
        $this->runs->markFailed($message->runId, $safeCode, 'The Aftercool import could not be completed.', Context::createDefaultContext());
    }

    private function safeCode(\Throwable $exception): string
    {
        if ($exception instanceof AfterCoolApiException) {
            return $exception->safeCode();
        }
        if ($exception instanceof UnrecoverableMessageHandlingException && $exception->getPrevious() instanceof AfterCoolApiException) {
            return $exception->getPrevious()->safeCode();
        }

        return 'aftercool_import_failed';
    }
}
