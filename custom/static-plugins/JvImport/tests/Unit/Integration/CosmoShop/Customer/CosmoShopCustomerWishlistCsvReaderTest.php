<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Customer;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerWishlistCsvReader;
use PHPUnit\Framework\TestCase;

final class CosmoShopCustomerWishlistCsvReaderTest extends TestCase
{
    public function testItNormalizesRegisteredGuestOrphanAndMalformedRows(): void
    {
        $file = $this->file([
            [' 42 ', ' 10 ', ' 100 ', ' SKU-100 '],
            ['0', '11', '101', 'SKU-101'],
            ['43', '12', '102', ''],
            ['invalid', '13', '103', 'SKU-103'],
            ['44', '14'],
        ]);

        try {
            $records = (new CosmoShopCustomerWishlistCsvReader())->read($file);

            self::assertCount(5, $records);
            self::assertSame(42, $records[0]->sourceCustomerId);
            self::assertSame(10, $records[0]->sourceListId);
            self::assertSame(100, $records[0]->sourceArticleId);
            self::assertSame('SKU-100', $records[0]->productNumber);
            self::assertTrue($records[0]->isWellFormed);
            self::assertSame(0, $records[1]->sourceCustomerId);
            self::assertTrue($records[1]->isWellFormed);
            self::assertSame('', $records[2]->productNumber);
            self::assertTrue($records[2]->isWellFormed);
            self::assertNull($records[3]->sourceCustomerId);
            self::assertFalse($records[3]->isWellFormed);
            self::assertFalse($records[4]->isWellFormed);
        } finally {
            unlink($file);
        }
    }

    public function testItRejectsAnUnexpectedHeader(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-wishlist-reader-');
        self::assertNotFalse($file);
        file_put_contents($file, "customer_id;product_number\n");

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('header is invalid');
            (new CosmoShopCustomerWishlistCsvReader())->read($file);
        } finally {
            unlink($file);
        }
    }

    public function testItReadsRfc4180QuotesWithoutTreatingBackslashesAsEscapes(): void
    {
        $productNumber = 'SKU-\\"QUOTED"';
        $file = $this->file([
            ['42', '10', '100', $productNumber],
        ]);

        try {
            $record = (new CosmoShopCustomerWishlistCsvReader())->read($file)[0];

            self::assertTrue($record->isWellFormed);
            self::assertSame($productNumber, $record->productNumber);
        } finally {
            unlink($file);
        }
    }

    /** @param list<list<string>> $rows */
    private function file(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-wishlist-reader-');
        self::assertNotFalse($file);
        $stream = fopen($file, 'wb');
        self::assertIsResource($stream);
        fputcsv($stream, ['source_customer_id', 'source_list_id', 'source_article_id', 'product_number'], ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '');
        }
        fclose($stream);

        return $file;
    }
}
