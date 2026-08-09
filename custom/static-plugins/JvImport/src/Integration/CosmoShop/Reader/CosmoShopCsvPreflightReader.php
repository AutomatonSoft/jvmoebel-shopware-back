<?php declare(strict_types=1);

namespace Jv\CatalogImport\Integration\CosmoShop\Reader;

use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Reader\CsvReader;
use Shopware\Core\Content\ImportExport\Struct\Config;

final class CosmoShopCsvPreflightReader extends AbstractReader
{
    private bool $preflighted = false;

    public function __construct(private readonly CsvReader $inner = new CsvReader())
    {
    }

    public function read(Config $config, $resource, int $offset): iterable
    {
        if (!$this->preflighted && 0 === $offset) {
            $this->preflight($config, $resource);
            $this->preflighted = true;
        }

        yield from $this->inner->read($config, $resource, $offset);
    }

    public function getOffset(): int
    {
        return $this->inner->getOffset();
    }

    /** @param resource $resource */
    private function preflight(Config $config, $resource): void
    {
        if (!is_resource($resource)) {
            throw ImportExportException::processingError('CosmoShop CSV file cannot be read.');
        }

        $delimiter = (string) ($config->get('delimiter') ?? ';');
        $enclosure = (string) ($config->get('enclosure') ?? '"');
        $escape = (string) ($config->get('escape') ?? '\\');
        $initialOffset = ftell($resource);
        if (false === $initialOffset || 0 !== fseek($resource, 0)) {
            throw ImportExportException::processingError('CosmoShop CSV file must be seekable.');
        }

        try {
            $headers = $this->nextRecord($resource, $delimiter, $enclosure, $escape);
            if (null === $headers) {
                throw ImportExportException::processingError('CosmoShop CSV file is empty or has no header row.');
            }

            $headers = array_map(static fn (mixed $header): string => trim((string) $header), $headers);
            if (in_array('', $headers, true)) {
                throw ImportExportException::processingError('CosmoShop CSV header contains an empty column name.');
            }

            $duplicates = array_keys(array_filter(array_count_values($headers), static fn (int $count): bool => $count > 1));
            if ([] !== $duplicates) {
                throw ImportExportException::processingError(sprintf('CosmoShop CSV header contains duplicate column(s): %s.', implode(', ', $duplicates)));
            }

            $required = [];
            foreach ($config->getMapping()->getElements() as $mapping) {
                if ($mapping->isRequiredByUser()) {
                    $required[] = $mapping->getMappedKey();
                }
            }

            $missing = array_values(array_diff($required, $headers));
            if ([] !== $missing) {
                throw ImportExportException::processingError(sprintf('CosmoShop CSV header is missing required column(s): %s.', implode(', ', $missing)));
            }

            $firstProduct = $this->nextRecord($resource, $delimiter, $enclosure, $escape);
            if (null === $firstProduct) {
                throw ImportExportException::processingError('CosmoShop CSV file contains no product rows.');
            }
            if (count($firstProduct) !== count($headers)) {
                throw ImportExportException::processingError(sprintf('CosmoShop CSV product row has %d columns; expected %d.', count($firstProduct), count($headers)));
            }
        } finally {
            fseek($resource, $initialOffset);
        }
    }

    /** @param resource $resource
     * @return list<mixed>|null
     */
    private function nextRecord($resource, string $delimiter, string $enclosure, string $escape): ?array
    {
        while (($record = fgetcsv($resource, 0, $delimiter, $enclosure, $escape)) !== false) {
            if (1 === count($record) && null === $record[0]) {
                continue;
            }

            return $record;
        }

        return null;
    }
}
