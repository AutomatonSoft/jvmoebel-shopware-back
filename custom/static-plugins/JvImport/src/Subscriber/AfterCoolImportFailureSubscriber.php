<?php declare(strict_types=1);

namespace Jv\Import\Subscriber;

use Jv\Import\Integration\AfterCool\Exception\AfterCoolApiException;
use Jv\Import\Message\AfterCoolImportPageMessage;
use Jv\Import\Service\AfterCool\Persistence\AfterCoolImportRunStore;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

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
        $safeCode = $exception instanceof AfterCoolApiException ? $exception->safeCode() : 'aftercool_import_failed';
        $this->logger->error('Aftercool import run failed after Messenger retries.', [
            'runId' => $message->runId,
            'offset' => $message->offset,
            'exception' => $exception,
        ]);
        $this->runs->markFailed($message->runId, $safeCode, 'The Aftercool import could not be completed.', Context::createDefaultContext());
    }
}
