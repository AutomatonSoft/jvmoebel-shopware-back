<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

use Jv\Import\Service\OrderImport\Dto\InvalidOrderRecord;

/** Streams the CosmoShop order JSONL file without loading it into memory. It only decodes lines; it never validates their shape. */
final class CosmoShopOrderJsonlReader
{
    /** @return \Generator<int, array<string, mixed>|InvalidOrderRecord> */
    public function read(string $file): \Generator
    {
        $stream = fopen($file, 'rb');
        if (false === $stream) {
            throw new \InvalidArgumentException('The order JSONL file cannot be read.');
        }

        try {
            while (false !== ($line = fgets($stream))) {
                $line = rtrim($line, "\r\n");
                if ('' === $line) {
                    yield new InvalidOrderRecord();
                    continue;
                }
                try {
                    $record = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    yield new InvalidOrderRecord();
                    continue;
                }
                if (!is_array($record)) {
                    yield new InvalidOrderRecord();
                    continue;
                }
                yield $record;
            }
        } finally {
            fclose($stream);
        }
    }
}
