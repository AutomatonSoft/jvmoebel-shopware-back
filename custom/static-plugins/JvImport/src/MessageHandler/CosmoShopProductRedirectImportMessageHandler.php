<?php declare(strict_types=1);

namespace Jv\Import\MessageHandler;

use Jv\Import\Message\CosmoShopProductRedirectImportMessage;
use Jv\Import\Service\ProductImport\Seo\ImportCosmoShopProductRedirectsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CosmoShopProductRedirectImportMessageHandler
{
    public function __construct(
        private ImportCosmoShopProductRedirectsService $service,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CosmoShopProductRedirectImportMessage $message): void
    {
        $result = $this->service->execute($message->sourceImportLogId, Context::createDefaultContext());
        $this->logger->info('CosmoShop product legacy redirect import completed.', [
            'operation' => 'cosmoshop_product_redirect_import',
            'sourceImportLogId' => $message->sourceImportLogId,
            'rows' => $result->rows,
            'created' => $result->created,
            'updated' => $result->updated,
            'unchanged' => $result->unchanged,
            'manualPreserved' => $result->manualPreserved,
            'conflicts' => $result->conflicts,
            'invalid' => $result->invalid,
            'missingSourceIdentity' => $result->missingSourceIdentity,
            'missingProduct' => $result->missingProduct,
            'issues' => $result->issues,
        ]);
    }
}
