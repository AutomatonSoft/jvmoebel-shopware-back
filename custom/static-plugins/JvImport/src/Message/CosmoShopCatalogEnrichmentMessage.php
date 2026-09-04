<?php declare(strict_types=1);

namespace Jv\Import\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

final readonly class CosmoShopCatalogEnrichmentMessage implements AsyncMessageInterface
{
    public function __construct(public string $sourceImportLogId)
    {
    }
}
