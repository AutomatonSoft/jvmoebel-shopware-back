<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\ImportExport;

use Jv\Import\Integration\CosmoShop\Reader\CosmoShopCsvPreflightReader;
use Jv\Import\Integration\CosmoShop\Reader\CosmoShopPreflightFailureRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\ShopwareHttpException;

final class CosmoShopCsvPreflightReaderTest extends TestCase
{
    public function testItReadsAValidCsvAfterPreflight(): void
    {
        $reader = $this->reader();
        $resource = $this->resource("product_number;ean;price_gross;name\nSKU-001;4260174423463;119.00;Product\n");

        try {
            $rows = iterator_to_array($reader->read($this->config(), $resource, 0));
            self::assertSame('SKU-001', $rows[0]['product_number']);
            self::assertSame('4260174423463', $rows[0]['ean']);
            self::assertSame('119.00', $rows[0]['price_gross']);
            self::assertSame('Product', $rows[0]['name']);
        } finally {
            fclose($resource);
        }
    }

    #[DataProvider('invalidCsvFiles')]
    public function testItRejectsInvalidFileStructure(string $csv, string $message): void
    {
        $reader = $this->reader();
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
    }

    public function testItRecordsAndLogsTheRejectedDryRunPreflight(): void
    {
        $failureRegistry = new CosmoShopPreflightFailureRegistry();

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(
                self::callback(static fn (string $message): bool => in_array($message, [
                    'CosmoShop product import preflight started.',
                    'CosmoShop product import preflight rejected.',
                ], true)),
                self::isType('array'),
            );

        $reader = $this->reader($logger, $failureRegistry);
        $resource = $this->resource("product_number;price_gross;name\nSKU-001;119.00;Product\n");

        try {
            iterator_to_array($reader->read($this->config(), $resource, 0));
            self::fail('Expected the invalid CSV to be rejected.');
        } catch (ShopwareHttpException $exception) {
            self::assertSame('CosmoShop CSV header is missing required column(s): ean.', $exception->getMessage());
        } finally {
            fclose($resource);
        }

        self::assertSame('019fe627a3b371759aac22afeae59c7e', $failureRegistry->consumeFailedConsoleImportLogId());
    }

    public function testItMarksAMalformedRowInTheMiddleOfTheStream(): void
    {
        $reader = $this->reader();
        $resource = $this->resource("product_number;ean;price_gross;name\nSKU-001;4260174423463;119.00;First\nSKU-002;4260174423464;119.00\nSKU-003;4260174423465;119.00;Last\n");

        try {
            $rows = iterator_to_array($reader->read($this->config(), $resource, 0));
        } finally {
            fclose($resource);
        }

        self::assertCount(3, $rows);
        self::assertSame('CosmoShop CSV product row has 3 columns; expected 4.', $rows[1]['__cosmoshop_csv_row_error']);
        self::assertSame('SKU-003', $rows[2]['product_number']);
    }

    public function testItAcceptsUtf8BomBeforeTheFirstHeader(): void
    {
        $reader = $this->reader();
        $resource = $this->resource("\xEF\xBB\xBFproduct_number;ean;price_gross;name\nSKU-001;4260174423463;119.00;Product\n");

        try {
            $rows = iterator_to_array($reader->read($this->config(), $resource, 0));
        } finally {
            fclose($resource);
        }

        self::assertSame('SKU-001', $rows[0]['product_number']);
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

    private function reader(?LoggerInterface $logger = null, ?CosmoShopPreflightFailureRegistry $failureRegistry = null): CosmoShopCsvPreflightReader
    {
        return new CosmoShopCsvPreflightReader(
            $logger ?? self::createStub(LoggerInterface::class),
            $failureRegistry ?? new CosmoShopPreflightFailureRegistry(),
            '019fe627a3b371759aac22afeae59c7e',
            'jv_cosmoshop_product_jvmoebel_de',
            'test',
        );
    }
}
