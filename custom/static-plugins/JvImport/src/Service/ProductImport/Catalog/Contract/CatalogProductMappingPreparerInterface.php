<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Contract;

use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductMappingPreparationResult;

interface CatalogProductMappingPreparerInterface
{
    public function execute(
        string $sourceCsv,
        string $snapshotDirectory,
        string $outputDirectory,
        ?int $limit,
        ?\Closure $onProcessed = null,
    ): CatalogProductMappingPreparationResult;
}
