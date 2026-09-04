<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Reader;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\ShopwareHttpException;

final class CosmoShopCsvPreflightReader extends AbstractReader
{
    private bool $preflighted = false;

    private int $offset = 0;

    /** @var list<string> */
    private array $headers = [];

    /** @var array<string, true> */
    private array $conflictingProductNumbers = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CosmoShopPreflightFailureRegistry $failureRegistry,
        private readonly string $importLogId,
        private readonly string $profileName,
        private readonly string $environment,
    ) {
    }

    public function read(Config $config, $resource, int $offset): iterable
    {
        if (!$this->preflighted && 0 === $offset) {
            $this->logger->info('CosmoShop product import preflight started.', $this->logContext());

            try {
                $this->preflight($config, $resource);
            } catch (ShopwareHttpException $exception) {
                $this->logger->info('CosmoShop product import preflight rejected.', [
                    ...$this->logContext(),
                    'reason' => $exception->getMessage(),
                ]);
                $this->failureRegistry->recordPreflightRejected($this->importLogId);

                throw $exception;
            }

            $this->preflighted = true;
            $this->logger->info('CosmoShop product import preflight passed.', $this->logContext());
        }

        if (!is_resource($resource)) {
            throw ImportExportException::processingError('CosmoShop CSV file cannot be read.');
        }

        $delimiter = (string) ($config->get('delimiter') ?? ';');
        $enclosure = (string) ($config->get('enclosure') ?? '"');
        $escape = (string) ($config->get('escape') ?? '\\');
        $this->initializeHeaders($resource, $delimiter, $enclosure, $escape, $offset);

        while (($record = fgetcsv($resource, 0, $delimiter, $enclosure, $escape)) !== false) {
            $position = ftell($resource);
            $this->offset = false === $position ? $offset : $position;
            if (1 === count($record) && null === $record[0]) {
                continue;
            }

            $row = [];
            foreach ($this->headers as $index => $header) {
                $row[$header] = $record[$index] ?? '';
            }
            if (count($record) !== count($this->headers)) {
                $error = sprintf(
                    'CosmoShop CSV product row has %d columns; expected %d.',
                    count($record),
                    count($this->headers),
                );
                $row['__cosmoshop_csv_row_error'] = $error;
                $row['name'] = '__cosmoshop_csv_row_error__:'.$error;
            }
            $productNumber = trim($row['product_number'] ?? '');
            if (isset($this->conflictingProductNumbers[$productNumber])) {
                $error = sprintf('CosmoShop product number "%s" occurs with different EAN values in one CSV.', $productNumber);
                $row['__cosmoshop_csv_row_error'] = $error;
                $row['name'] = '__cosmoshop_csv_row_error__:'.$error;
            }

            if ([] !== array_filter($row, static fn (mixed $value): bool => '' !== $value)) {
                yield $row;
            }
        }
    }

    public function getOffset(): int
    {
        return $this->offset;
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

            $headers = $this->normalizeHeaders($headers);
            if (in_array('', $headers, true)) {
                throw ImportExportException::processingError('CosmoShop CSV header contains an empty column name.');
            }

            $duplicates = array_keys(array_filter(array_count_values($headers), static fn (int $count): bool => $count > 1));
            if ([] !== $duplicates) {
                throw ImportExportException::processingError(sprintf('CosmoShop CSV header contains duplicate column(s): %s.', implode(', ', $duplicates)));
            }

            $required = [
                'source_inactive',
                'stock',
                'weight',
                'length',
                'width',
                'height',
                'min_purchase',
                'contents',
                'reference_unit',
            ];
            foreach ($config->getMapping()->getElements() as $mapping) {
                if ($mapping->isRequiredByUser()) {
                    $required[] = $mapping->getMappedKey();
                }
            }

            $missing = array_values(array_diff(array_unique($required), $headers));
            if ([] !== $missing) {
                throw ImportExportException::processingError(sprintf('CosmoShop CSV header is missing required column(s): %s.', implode(', ', $missing)));
            }

            $firstProduct = $this->nextRecord($resource, $delimiter, $enclosure, $escape);
            if (null === $firstProduct) {
                throw ImportExportException::processingError('CosmoShop CSV file contains no product rows.');
            }

            $this->collectProductNumberEanConflicts($resource, $headers, $firstProduct, $delimiter, $enclosure, $escape);
        } finally {
            fseek($resource, $initialOffset);
        }
    }

    /**
     * @param resource     $resource
     * @param list<string> $headers
     * @param list<mixed>  $firstProduct
     */
    private function collectProductNumberEanConflicts($resource, array $headers, array $firstProduct, string $delimiter, string $enclosure, string $escape): void
    {
        $productNumberIndex = array_search('product_number', $headers, true);
        $eanIndex = array_search('ean', $headers, true);
        if (false === $productNumberIndex || false === $eanIndex) {
            return;
        }

        /** @var array<string, string> $eanByProductNumber */
        $eanByProductNumber = [];
        $this->collectProductNumberEanConflict($firstProduct, $headers, $productNumberIndex, $eanIndex, $eanByProductNumber);
        while (($record = $this->nextRecord($resource, $delimiter, $enclosure, $escape)) !== null) {
            $this->collectProductNumberEanConflict($record, $headers, $productNumberIndex, $eanIndex, $eanByProductNumber);
        }
    }

    /**
     * @param list<mixed>           $record
     * @param list<string>          $headers
     * @param array<string, string> $eanByProductNumber
     */
    private function collectProductNumberEanConflict(array $record, array $headers, int $productNumberIndex, int $eanIndex, array &$eanByProductNumber): void
    {
        if (count($headers) !== count($record)) {
            return;
        }
        $productNumber = trim((string) $record[$productNumberIndex]);
        $ean = trim((string) $record[$eanIndex]);
        if ('' === $productNumber || '' === $ean) {
            return;
        }
        if (isset($eanByProductNumber[$productNumber]) && $eanByProductNumber[$productNumber] !== $ean) {
            $this->conflictingProductNumbers[$productNumber] = true;
        }
        $eanByProductNumber[$productNumber] ??= $ean;
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

    /** @param resource $resource */
    private function initializeHeaders($resource, string $delimiter, string $enclosure, string $escape, int $offset): void
    {
        if ([] === $this->headers) {
            if (0 !== fseek($resource, 0)) {
                throw ImportExportException::processingError('CosmoShop CSV file must be seekable.');
            }
            $headers = $this->nextRecord($resource, $delimiter, $enclosure, $escape);
            if (null === $headers) {
                throw ImportExportException::processingError('CosmoShop CSV file is empty or has no header row.');
            }
            $this->headers = $this->normalizeHeaders($headers);
        }

        if (0 === $offset) {
            $position = ftell($resource);
            $this->offset = false === $position ? 0 : $position;

            return;
        }

        if (0 !== fseek($resource, $offset)) {
            throw ImportExportException::processingError('CosmoShop CSV file must be seekable.');
        }
        $this->offset = $offset;
    }

    /** @param list<mixed> $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $headers = array_map(static fn (mixed $header): string => trim((string) $header), $headers);
        if (isset($headers[0]) && str_starts_with($headers[0], "\xEF\xBB\xBF")) {
            $headers[0] = substr($headers[0], 3);
        }

        return $headers;
    }

    /** @return array{operation: string, importLogId: string, profile: string, environment: string} */
    private function logContext(): array
    {
        return [
            'operation' => 'cosmoshop_product_import_preflight',
            'importLogId' => $this->importLogId,
            'profile' => $this->profileName,
            'environment' => $this->environment,
        ];
    }
}
