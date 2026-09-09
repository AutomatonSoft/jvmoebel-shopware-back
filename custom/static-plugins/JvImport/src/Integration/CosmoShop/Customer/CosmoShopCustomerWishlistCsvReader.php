<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

final class CosmoShopCustomerWishlistCsvReader
{
    private const array HEADER = ['source_customer_id', 'source_list_id', 'source_article_id', 'product_number'];

    /** @return list<CosmoShopCustomerWishlistRecord> */
    public function read(string $file): array
    {
        $stream = fopen($file, 'rb');
        if (false === $stream) {
            throw new \InvalidArgumentException('The customer wishlist CSV file cannot be read.');
        }

        try {
            $header = fgetcsv($stream, separator: ';', enclosure: '"', escape: '');
            if (is_array($header) && isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
            }
            if (self::HEADER !== $header) {
                throw new \InvalidArgumentException('The customer wishlist CSV header is invalid.');
            }

            $records = [];
            while (($row = fgetcsv($stream, separator: ';', enclosure: '"', escape: '')) !== false) {
                $values = array_map(static fn (mixed $value): string => trim((string) $value), $row);
                $sourceCustomerId = $this->nonNegativeInteger($values[0]);
                $sourceListId = $this->positiveInteger($values[1] ?? '');
                $sourceArticleId = $this->positiveInteger($values[2] ?? '');
                $records[] = new CosmoShopCustomerWishlistRecord(
                    $sourceCustomerId,
                    $sourceListId,
                    $sourceArticleId,
                    $values[3] ?? '',
                    count(self::HEADER) === count($row) && null !== $sourceCustomerId && null !== $sourceListId && null !== $sourceArticleId,
                );
            }

            return $records;
        } finally {
            fclose($stream);
        }
    }

    private function nonNegativeInteger(string $value): ?int
    {
        if (1 !== preg_match('/^[0-9]+$/', $value)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return false === $integer ? null : $integer;
    }

    private function positiveInteger(string $value): ?int
    {
        if (1 !== preg_match('/^[0-9]+$/', $value)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return false === $integer ? null : $integer;
    }
}
