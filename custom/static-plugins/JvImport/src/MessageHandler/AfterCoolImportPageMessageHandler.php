<?php declare(strict_types=1);

namespace Jv\Import\MessageHandler;

use Jv\Import\Integration\AfterCool\Exception\AfterCoolApiException;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolResponseContractException;
use Jv\Import\Message\AfterCoolImportPageMessage;
use Jv\Import\Service\AfterCool\Import\ImportAfterCoolPageService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class AfterCoolImportPageMessageHandler
{
    public function __construct(private ImportAfterCoolPageService $processor, private MessageBusInterface $messageBus)
    {
    }

    public function __invoke(AfterCoolImportPageMessage $message): void
    {
        try {
            $result = $this->processor->process($message->runId, $message->offset, Context::createDefaultContext());
        } catch (AfterCoolApiException $exception) {
            if (!$exception->isRetryable()) {
                throw new UnrecoverableMessageHandlingException('Aftercool returned a permanent API error.', previous: $exception);
            }

            throw $exception;
        } catch (AfterCoolResponseContractException $exception) {
            throw new UnrecoverableMessageHandlingException('Aftercool response does not satisfy the import contract.', previous: $exception);
        }
        if (null !== $result->nextOffset) {
            $this->messageBus->dispatch(new AfterCoolImportPageMessage($message->runId, $result->nextOffset));
        }
    }
}
