<?php declare(strict_types=1);

namespace Jv\CatalogImport\Tests\Unit\ImportExport;

use Jv\CatalogImport\Integration\CosmoShop\Reader\CosmoShopCsvPreflightReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\ShopwareHttpException;

final class CosmoShopCsvPreflightReaderTest extends TestCase
{
    public function testItReadsAValidCsvAfterPreflight(): void
    {
        $reader = new CosmoShopCsvPreflightReader();
        $resource = $this->resource("product_number;ean;price_gross;name\nSKU-001;4260174423463;119.00;Product\n");

        try {
            self::assertSame([[
                'product_number' => 'SKU-001',
                'ean' => '4260174423463',
                'price_gross' => '119.00',
                'name' => 'Product',
            ]], iterator_to_array($reader->read($this->config(), $resource, 0)));
        } finally {
            fclose($resource);
        }
    }

    #[DataProvider('invalidCsvFiles')]
    public function testItRejectsInvalidFileStructure(string $csv, string $message): void
    {
        $reader = new CosmoShopCsvPreflightReader();
        $resource = $this->resource($csv);

        try {
            $this->expectException(ShopwareHttpException::class);
            $this->expectExceptionMessage($message);
            iterator_to_array($reader->read($this->config(), $resource, 0));
        } finally {
            fclose($resource);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidCsvFiles(): iterable
    {
        yield 'empty file' => ['', 'CosmoShop CSV file is empty or has no header row.'];
        yield 'no expected header' => ["SKU-001;4260174423463;119.00;Product\n", 'CosmoShop CSV header is missing required column(s): product_number, ean, price_gross, name.'];
        yield 'duplicate header' => ["product_number;ean;ean;price_gross;name\nSKU-001;4260174423463;4260174423463;119.00;Product\n", 'CosmoShop CSV header contains duplicate column(s): ean.'];
        yield 'missing required header' => ["product_number;price_gross;name\nSKU-001;119.00;Product\n", 'CosmoShop CSV header is missing required column(s): ean.'];
        yield 'header only' => ["product_number;ean;price_gross;name\n", 'CosmoShop CSV file contains no product rows.'];
        yield 'wrong product column count' => ["product_number;ean;price_gross;name\nSKU-001;4260174423463;119.00;Product;extra\n", 'CosmoShop CSV product row has 5 columns; expected 4.'];
    }

    /** @return resource */
    private function resource(string $contents)
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, $contents);
        rewind($resource);

        return $resource;
    }

    private function config(): Config
    {
        return new Config([
            ['key' => 'productNumber', 'mappedKey' => 'product_number', 'requiredByUser' => true],
            ['key' => 'ean', 'mappedKey' => 'ean', 'requiredByUser' => true],
            ['key' => 'price.DEFAULT.gross', 'mappedKey' => 'price_gross', 'requiredByUser' => true],
            ['key' => 'translations.de-DE.name', 'mappedKey' => 'name', 'requiredByUser' => true],
        ], [], []);
    }
}
