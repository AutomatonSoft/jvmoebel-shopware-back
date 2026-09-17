<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

enum CatalogEnrichmentSource: string
{
    case CosmoShop = 'jvCatalogEnrichmentSourceImportLogId';
    case AfterCool = 'jvCatalogEnrichmentAfterCoolRunId';

    public function activatesParents(): bool
    {
        return self::AfterCool === $this;
    }
}
