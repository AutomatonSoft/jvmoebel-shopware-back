<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

final class CosmoShopOrderJsonlReader
{
    public function __construct(private readonly ?CosmoShopOrderNormalizer $normalizer = null)
    {
    }

    /** @return \Generator<int, array{record?: CosmoShopOrderData, invalid?: true}> */
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
                    yield ['invalid' => true];
                    continue;
                }
                try {
                    $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    yield ['invalid' => true];
                    continue;
                }
                if (!is_array($record)) {
                    yield ['invalid' => true];
                    continue;
                }
                $normalized = ($this->normalizer ?? new CosmoShopOrderNormalizer())->normalize($record);
                if (null === $normalized) {
                    yield ['invalid' => true];
                    continue;
                }
                yield ['record' => $normalized];
            }
        } finally {
            fclose($stream);
        }
    }
}
