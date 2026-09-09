<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

final class CosmoShopCustomerPasswordCsvReader
{
    /**
     * @return list<CosmoShopCustomerPasswordRecord>
     */
    public function read(string $file): array
    {
        $stream = fopen($file, 'rb');
        if (false === $stream) {
            throw new \InvalidArgumentException('The password CSV file cannot be read.');
        }

        try {
            $header = fgetcsv($stream, separator: ';', enclosure: '"', escape: '');
            if ($header !== ['source_customer_id', 'password_hash', 'salt']) {
                throw new \InvalidArgumentException('The password CSV header is invalid.');
            }

            $records = [];
            while (($row = fgetcsv($stream, separator: ';', enclosure: '"', escape: '')) !== false) {
                $sourceCustomerId = isset($row[0]) && ctype_digit($row[0]) ? (int) $row[0] : null;
                $records[] = new CosmoShopCustomerPasswordRecord(
                    $sourceCustomerId,
                    $row[1] ?? '',
                    $row[2] ?? '',
                    3 === count($row) && null !== $sourceCustomerId,
                );
            }

            return $records;
        } finally {
            fclose($stream);
        }
    }
}
