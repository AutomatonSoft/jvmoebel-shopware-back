<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductInvalidRecord;

final class CatalogProductInvalidRecordsCsvWriter
{
    /** @param list<CatalogProductInvalidRecord> $invalidRecords */
    public function write(string $path, array $invalidRecords): void
    {
        $handle = fopen($path, 'wb');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Unable to write invalid catalog records to "%s".', $path));
        }
        try {
            $this->writeRow($handle, ['source_code', 'product_number', 'ean', 'reason']);
            foreach ($invalidRecords as $invalidRecord) {
                $this->writeRow($handle, [
                    $invalidRecord->sourceCode,
                    $invalidRecord->productNumber,
                    $invalidRecord->ean,
                    $invalidRecord->reason,
                ]);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param resource $handle
     * @param list<string> $row
     */
    private function writeRow($handle, array $row): void
    {
        if (false === fputcsv($handle, $row, ';', '"', '\\')) {
            throw new \RuntimeException('Unable to write invalid catalog record CSV row.');
        }
    }
}
