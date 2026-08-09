<?php declare(strict_types=1);

namespace Jv\CatalogImport\Service\ProductImport\Contract;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;

interface ProductImportRecordPreparer
{
    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $mappedRecord
     *
     * @return array<string, mixed>
     */
    public function execute(Market $market, array $row, array $mappedRecord, string $languageId): array;
}
