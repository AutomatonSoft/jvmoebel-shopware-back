<?php declare(strict_types=1);

namespace Jv\Import\MessageHandler;

use Jv\Import\Message\CosmoShopCatalogEnrichmentMessage;
use Jv\Import\Service\ProductImport\Catalog\EnrichCosmoShopImportService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CosmoShopCatalogEnrichmentMessageHandler
{
    public function __construct(private EnrichCosmoShopImportService $service)
    {
    }

    public function __invoke(CosmoShopCatalogEnrichmentMessage $message): void
    {
        $this->service->execute($message->sourceImportLogId, Context::createDefaultContext());
    }
}
