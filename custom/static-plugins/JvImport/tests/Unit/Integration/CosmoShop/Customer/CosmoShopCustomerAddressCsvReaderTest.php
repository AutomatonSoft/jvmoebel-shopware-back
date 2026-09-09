<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Customer;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerAddressCsvReader;
use PHPUnit\Framework\TestCase;

final class CosmoShopCustomerAddressCsvReaderTest extends TestCase
{
    public function testItNormalizesValuesAndMarksMalformedRows(): void
    {
        $file = $this->file([
            [' 42 ', ' 17 ', ' MR ', ' Dr. ', ' Ada ', ' Lovelace ', ' ACME ', ' Main 1 ', ' 10115 ', ' Berlin ', ' de ', ' +49 1 '],
            ['invalid', '18', 'mr', '', 'Bad', 'Customer', '', 'Street 1', '', 'Berlin', 'DE', ''],
            ['43', '19'],
        ]);

        try {
            $records = (new CosmoShopCustomerAddressCsvReader())->read($file);

            self::assertCount(3, $records);
            self::assertSame(42, $records[0]->sourceCustomerId);
            self::assertSame(17, $records[0]->sourceAddressId);
            self::assertSame('mr', $records[0]->salutation);
            self::assertSame('Ada', $records[0]->firstName);
            self::assertSame('DE', $records[0]->country);
            self::assertTrue($records[0]->isWellFormed);
            self::assertNull($records[1]->sourceCustomerId);
            self::assertFalse($records[1]->isWellFormed);
            self::assertFalse($records[2]->isWellFormed);
        } finally {
            unlink($file);
        }
    }

    public function testItRejectsAnUnexpectedHeader(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-address-reader-');
        self::assertNotFalse($file);
        file_put_contents($file, "customer_id;address_id\n");

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('header is invalid');
            (new CosmoShopCustomerAddressCsvReader())->read($file);
        } finally {
            unlink($file);
        }
    }

    public function testItReadsRfc4180QuotesWithoutTreatingBackslashesAsEscapes(): void
    {
        $company = 'ACME \\"Quoted"';
        $file = $this->file([
            ['42', '17', 'mr', '', 'Ada', 'Lovelace', $company, 'Main 1', '10115', 'Berlin', 'DE', ''],
        ]);

        try {
            $record = (new CosmoShopCustomerAddressCsvReader())->read($file)[0];

            self::assertTrue($record->isWellFormed);
            self::assertSame($company, $record->company);
        } finally {
            unlink($file);
        }
    }

    /** @param list<list<string>> $rows */
    private function file(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-address-reader-');
        self::assertNotFalse($file);
        $stream = fopen($file, 'wb');
        self::assertIsResource($stream);
        fputcsv($stream, [
            'source_customer_id', 'source_address_id', 'salutation', 'title', 'first_name', 'last_name',
            'company', 'street', 'zipcode', 'city', 'country', 'phone_number',
        ], ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '');
        }
        fclose($stream);

        return $file;
    }
}
