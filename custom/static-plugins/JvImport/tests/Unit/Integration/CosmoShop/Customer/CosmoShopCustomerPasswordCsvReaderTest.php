<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Customer;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerPasswordCsvReader;
use PHPUnit\Framework\TestCase;

final class CosmoShopCustomerPasswordCsvReaderTest extends TestCase
{
    public function testItNormalizesMalformedRecords(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-password-reader-');
        self::assertNotFalse($file);
        file_put_contents($file, "source_customer_id;password_hash;salt\n42;plaintext;\ninvalid;hash;salt\n43;only-two-columns\n");

        try {
            $records = (new CosmoShopCustomerPasswordCsvReader())->read($file);

            self::assertCount(3, $records);
            self::assertSame(42, $records[0]->sourceCustomerId);
            self::assertTrue($records[0]->isWellFormed);
            self::assertNull($records[1]->sourceCustomerId);
            self::assertFalse($records[1]->isWellFormed);
            self::assertSame(43, $records[2]->sourceCustomerId);
            self::assertFalse($records[2]->isWellFormed);
        } finally {
            unlink($file);
        }
    }

    public function testItRejectsAnUnexpectedHeader(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-password-reader-');
        self::assertNotFalse($file);
        file_put_contents($file, "customer_id;password;pepper\n");

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('header is invalid');
            (new CosmoShopCustomerPasswordCsvReader())->read($file);
        } finally {
            unlink($file);
        }
    }

    public function testItRoundTripsEscapedPasswordMaterial(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-password-reader-');
        self::assertNotFalse($file);
        $stream = fopen($file, 'wb');
        self::assertNotFalse($stream);
        fputcsv($stream, ['source_customer_id', 'password_hash', 'salt'], ';', '"', '');
        $password = 'plain-\\"quoted"-value';
        fputcsv($stream, ['44', $password, ''], ';', '"', '');
        fclose($stream);

        try {
            $record = (new CosmoShopCustomerPasswordCsvReader())->read($file)[0];

            self::assertTrue($record->isWellFormed);
            self::assertSame($password, $record->password);
        } finally {
            unlink($file);
        }
    }
}
