<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

final class CosmoShopCustomerAddressCsvReader
{
    private const array HEADER = [
        'source_customer_id',
        'source_address_id',
        'salutation',
        'title',
        'first_name',
        'last_name',
        'company',
        'street',
        'zipcode',
        'city',
        'country',
        'phone_number',
    ];

    /** @return list<CosmoShopCustomerAddressRecord> */
    public function read(string $file): array
    {
        $stream = fopen($file, 'rb');
        if (false === $stream) {
            throw new \InvalidArgumentException('The customer address CSV file cannot be read.');
        }

        try {
            $header = fgetcsv($stream, separator: ';', enclosure: '"', escape: '');
            if (is_array($header) && isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
            }
            if (self::HEADER !== $header) {
                throw new \InvalidArgumentException('The customer address CSV header is invalid.');
            }

            $records = [];
            while (($row = fgetcsv($stream, separator: ';', enclosure: '"', escape: '')) !== false) {
                $values = array_map(static fn (mixed $value): string => trim((string) $value), $row);
                $sourceCustomerId = $this->positiveInteger($values[0]);
                $sourceAddressId = $this->positiveInteger($values[1] ?? '');

                $records[] = new CosmoShopCustomerAddressRecord(
                    $sourceCustomerId,
                    $sourceAddressId,
                    mb_strtolower($values[2] ?? ''),
                    $values[3] ?? '',
                    $values[4] ?? '',
                    $values[5] ?? '',
                    $values[6] ?? '',
                    $values[7] ?? '',
                    $values[8] ?? '',
                    $values[9] ?? '',
                    mb_strtoupper($values[10] ?? ''),
                    $values[11] ?? '',
                    count(self::HEADER) === count($row) && null !== $sourceCustomerId && null !== $sourceAddressId,
                );
            }

            return $records;
        } finally {
            fclose($stream);
        }
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
