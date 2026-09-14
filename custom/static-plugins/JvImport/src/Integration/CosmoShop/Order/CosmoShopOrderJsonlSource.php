<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

use Jv\Import\Service\OrderImport\Contract\OrderSourceInterface;
use Jv\Import\Service\OrderImport\Dto\InvalidOrderRecord;
use Jv\Import\Service\OrderImport\Dto\OrderImportData;

/** CosmoShop implementation of the order source boundary: streaming JSONL reader plus structural/vocabulary normalizer. */
final readonly class CosmoShopOrderJsonlSource implements OrderSourceInterface
{
    public function __construct(
        private CosmoShopOrderJsonlReader $reader,
        private CosmoShopOrderNormalizer $normalizer,
    ) {
    }

    /** @return iterable<OrderImportData|InvalidOrderRecord> */
    public function read(string $location): iterable
    {
        foreach ($this->reader->read($location) as $item) {
            yield $item instanceof InvalidOrderRecord ? $item : $this->normalizer->normalize($item);
        }
    }
}
