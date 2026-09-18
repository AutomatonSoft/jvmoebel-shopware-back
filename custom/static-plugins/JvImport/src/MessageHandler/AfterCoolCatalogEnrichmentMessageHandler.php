<?php declare(strict_types=1);

namespace Jv\Import\MessageHandler;

use Jv\Import\Message\AfterCoolCatalogEnrichmentMessage;
use Jv\Import\Service\AfterCool\Import\EnrichAfterCoolImportService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AfterCoolCatalogEnrichmentMessageHandler
{
    public function __construct(private EnrichAfterCoolImportService $service)
    {
    }

    public function __invoke(AfterCoolCatalogEnrichmentMessage $message): void
    {
        $this->service->execute($message->runId, Context::createDefaultContext());
    }
}
