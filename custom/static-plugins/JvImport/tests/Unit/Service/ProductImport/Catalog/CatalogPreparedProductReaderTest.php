<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\Catalog;

use Jv\Import\Integration\Csv\SemicolonCsvReader;
use Jv\Import\Service\ProductImport\Catalog\CatalogPreparedProductReader;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductAttribute;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductData;
use PHPUnit\Framework\TestCase;

final class CatalogPreparedProductReaderTest extends TestCase
{
    public function testItReadsPreparedProductsAndTypedAttributeRows(): void
    {
        $products = $this->file(<<<'CSV'
product_number;ean;product_reference;category_id;category_group_id;standard_price_amount;currency
4260454043503;4260454043503;model-1;25922;3446;1200.50;EUR
CSV);
        $attributes = $this->file(<<<'CSV'
product_number;ean;attribute_name;values_json
4260454043503;4260454043503;Color;["Brown"]
4260454043503;4260454043503;Width;["120,5"]
CSV);

        try {
            self::assertEquals([
                new CatalogProductData('source-a', '4260454043503', '4260454043503', 'model-1', '25922', '3446', 1200.5, 'EUR', [
                    new CatalogProductAttribute('Color', ['Brown']),
                    new CatalogProductAttribute('Width', ['120,5']),
                ]),
            ], (new CatalogPreparedProductReader(new SemicolonCsvReader()))->read('source-a', $products, $attributes));
        } finally {
            unlink($products);
            unlink($attributes);
        }
    }

    public function testItAcceptsTheLastIdenticalProductRow(): void
    {
        $products = $this->file(<<<'CSV'
product_number;ean;product_reference;category_id;category_group_id;standard_price_amount;currency
4260454043503;4260454043503;old-model;25922;3446;1000;EUR
4260454043503;4260454043503;new-model;25923;3446;1200;EUR
CSV);
        $attributes = $this->file("product_number;ean;attribute_name;values_json\n");

        try {
            $result = (new CatalogPreparedProductReader(new SemicolonCsvReader()))->read('source-a', $products, $attributes);
            self::assertSame('new-model', $result[0]->productReference);
            self::assertSame('25923', $result[0]->categoryId);
            self::assertSame(1200.0, $result[0]->standardPriceAmount);
        } finally {
            unlink($products);
            unlink($attributes);
        }
    }

    public function testItRejectsTwoDifferentEansForTheSameProductNumber(): void
    {
        $products = $this->file(<<<'CSV'
product_number;ean;product_reference;category_id;category_group_id;standard_price_amount;currency
sku-1;4260454043503;model-1;25922;3446;1000;EUR
sku-1;4260454043504;model-1;25922;3446;1000;EUR
CSV);
        $attributes = $this->file("product_number;ean;attribute_name;values_json\n");

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('has several EAN values');
            (new CatalogPreparedProductReader(new SemicolonCsvReader()))->read('source-a', $products, $attributes);
        } finally {
            unlink($products);
            unlink($attributes);
        }
    }

    public function testItRejectsAnAttributeWhoseEanDoesNotMatchTheProductRow(): void
    {
        $products = $this->file("product_number;ean;product_reference;category_id;category_group_id;standard_price_amount;currency\nsku-1;4260454043503;model-1;25922;3446;1000;EUR\n");
        $attributes = $this->file("product_number;ean;attribute_name;values_json\nsku-1;4260454043504;Color;[\"Brown\"]\n");

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('EAN does not match');
            (new CatalogPreparedProductReader(new SemicolonCsvReader()))->read('source-a', $products, $attributes);
        } finally {
            unlink($products);
            unlink($attributes);
        }
    }

    public function testItRejectsMalformedOrNonStringAttributeValues(): void
    {
        $products = $this->file("product_number;ean;product_reference;category_id;category_group_id;standard_price_amount;currency\nsku-1;4260454043503;model-1;25922;3446;1000;EUR\n");
        $attributes = $this->file("product_number;ean;attribute_name;values_json\nsku-1;4260454043503;Color;[1]\n");

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('must contain only strings');
            (new CatalogPreparedProductReader(new SemicolonCsvReader()))->read('source-a', $products, $attributes);
        } finally {
            unlink($products);
            unlink($attributes);
        }
    }

    private function file(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'jv-catalog-products-');
        self::assertNotFalse($file);
        file_put_contents($file, $contents);

        return $file;
    }
}
