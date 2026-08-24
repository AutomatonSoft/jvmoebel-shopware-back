<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\Catalog;

use Jv\Import\Integration\Csv\SemicolonCsvReader;
use Jv\Import\Service\ProductImport\Catalog\PrepareCatalogShopwareImportCsvService;
use PHPUnit\Framework\TestCase;

final class PrepareCatalogShopwareImportCsvServiceTest extends TestCase
{
    public function testItStreamsOneParentAndOneChildRowForEachPreparedProduct(): void
    {
        $directory = sys_get_temp_dir().'/jv-catalog-shopware-csv-'.bin2hex(random_bytes(8));
        mkdir($directory, 0775, true);
        $products = $directory.'/products.csv';
        $attributes = $directory.'/attributes.csv';
        $output = $directory.'/shopware.csv';
        file_put_contents($products, "product_number;ean;category_name;category_id;category_group_id;standard_price_amount;currency\nSKU-1;4260454043503;Sofas;25922;3446;1200;EUR\n");
        file_put_contents($attributes, "product_number;ean;attribute_name;values_json\nSKU-1;4260454043503;Color;[\"Brown\"]\nSKU-1;4260454043503;Width;[\"120\"]\n");

        try {
            $result = (new PrepareCatalogShopwareImportCsvService(new SemicolonCsvReader()))->execute($products, $attributes, $output);

            self::assertSame(2, $result);
            self::assertSame([
                'record_type;product_number;ean;category_id;category_group_id;standard_price_amount;currency;attributes_json',
                'parent;SKU-1;4260454043503;25922;3446;1200;EUR;[["Color",["Brown"]],["Width",["120"]]]',
                'child;SKU-1;4260454043503;25922;3446;1200;EUR;[["Color",["Brown"]],["Width",["120"]]]',
            ], explode("\n", trim((string) file_get_contents($output))));
        } finally {
            unlink($products);
            unlink($attributes);
            unlink($output);
            rmdir($directory);
        }
    }

    public function testItRejectsAnAttributeThatIsOutOfProductOrder(): void
    {
        $directory = sys_get_temp_dir().'/jv-catalog-shopware-csv-'.bin2hex(random_bytes(8));
        mkdir($directory, 0775, true);
        $products = $directory.'/products.csv';
        $attributes = $directory.'/attributes.csv';
        $output = $directory.'/shopware.csv';
        file_put_contents($products, "product_number;ean;category_name;category_id;category_group_id;standard_price_amount;currency\nSKU-1;4260454043503;Sofas;25922;3446;1200;EUR\n");
        file_put_contents($attributes, "product_number;ean;attribute_name;values_json\nSKU-2;4260454043504;Color;[\"Brown\"]\n");

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('references unknown product number "SKU-2"');
            (new PrepareCatalogShopwareImportCsvService(new SemicolonCsvReader()))->execute($products, $attributes, $output);
        } finally {
            if (is_file($products)) {
                unlink($products);
            }
            if (is_file($attributes)) {
                unlink($attributes);
            }
            if (is_file($output)) {
                unlink($output);
            }
            rmdir($directory);
        }
    }
}
