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
        yield 'wrong product column count' => ["product_number;ean;price_gross;name\nSKU-001;4260174423463;119.00;Product;extra\n", 'CosmoShop CSV product row has 5 columns; expected 4.'];
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

        self::assertSame('019fe627a3b371759aac22afeae59c7e', $failureRegistry->consumeFailedDryRunLogId());
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
            new \Shopware\Core\Content\ImportExport\Processing\Reader\CsvReader(),
            $logger ?? $this->createStub(LoggerInterface::class),
            $failureRegistry ?? new CosmoShopPreflightFailureRegistry(),
            '019fe627a3b371759aac22afeae59c7e',
            'jv_cosmoshop_product_jvmoebel_de',
            'test',
            true,
        );
    }
}
