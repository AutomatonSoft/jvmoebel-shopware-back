<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\Okb\Service;

use Jv\Import\Integration\Csv\SemicolonCsvReader;
use Jv\Import\Integration\Okb\OkbProductApiClient;
use Jv\Import\Integration\Okb\OkbProductResponseNormalizer;
use Jv\Import\Integration\Okb\Service\PrepareOkbProductMappingService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PrepareOkbProductMappingServiceTest extends TestCase
{
    public function testItKeepsPreviousOutputsWhenPreparationAborts(): void
    {
        $directory = sys_get_temp_dir().'/jv-okb-product-mapping-'.bin2hex(random_bytes(8));
        mkdir($directory.'/snapshot', 0775, true);
        mkdir($directory.'/output', 0775, true);
        file_put_contents($directory.'/source.csv', "product_number;ean\n4260454043503;4260454043503\nbroken\n");
        file_put_contents($directory.'/snapshot/okb-categories.csv', "category_group_id;category_id;category_name\ngroup-1;category-1;Sofas\n");
        file_put_contents($directory.'/snapshot/okb-attributes.csv', "category_group_id;attribute_id;attribute_name\ngroup-1;color;Color\n");
        foreach (['okb-product-mapping.csv', 'okb-product-attributes.csv', 'okb-product-mapping-failures.csv'] as $file) {
            file_put_contents($directory.'/output/'.$file, 'previous '.$file);
        }
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->with(false)->willReturn(['productVariations' => [[
            'productReference' => '4260454043503', 'sku' => '4260454043503', 'ean' => '4260454043503',
            'productDescription' => ['category' => 'Sofas', 'attributes' => []],
        ]]]);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        try {
            $this->expectException(\InvalidArgumentException::class);
            (new PrepareOkbProductMappingService(new SemicolonCsvReader(), new OkbProductApiClient($httpClient, new OkbProductResponseNormalizer(), 'https://okb.example')))->execute($directory.'/source.csv', $directory.'/snapshot', $directory.'/output', null);
        } finally {
            foreach (['okb-product-mapping.csv', 'okb-product-attributes.csv', 'okb-product-mapping-failures.csv'] as $file) {
                self::assertSame('previous '.$file, file_get_contents($directory.'/output/'.$file));
            }
            self::assertSame([], false !== glob($directory.'/output/*.tmp.*') ? glob($directory.'/output/*.tmp.*') : []);
            array_map(unlink(...), false !== ($outputs = glob($directory.'/output/*')) ? $outputs : []);
            array_map(unlink(...), false !== ($snapshotFiles = glob($directory.'/snapshot/*')) ? $snapshotFiles : []);
            unlink($directory.'/source.csv');
            rmdir($directory.'/output');
            rmdir($directory.'/snapshot');
            rmdir($directory);
        }
    }

    public function testItIgnoresAnAttributeThatIsNotInTheProductsCategoryGroupSchema(): void
    {
        $directory = sys_get_temp_dir().'/jv-okb-product-mapping-'.bin2hex(random_bytes(8));
        mkdir($directory.'/snapshot', 0775, true);
        mkdir($directory.'/output', 0775, true);
        file_put_contents($directory.'/source.csv', "product_number;ean\n4260454043503;4260454043503\n");
        file_put_contents($directory.'/snapshot/okb-categories.csv', "category_group_id;category_id;category_name\ngroup-1;category-1;Sofas\n");
        file_put_contents($directory.'/snapshot/okb-attributes.csv', "category_group_id;attribute_id;attribute_name\ngroup-1;color;Color\n");
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->with(false)->willReturn([
            'productVariations' => [[
                'productReference' => '4260454043503',
                'sku' => '4260454043503',
                'ean' => '4260454043503',
                'productDescription' => [
                    'category' => 'Sofas',
                    'attributes' => [['name' => 'Leg color', 'values' => ['black']]],
                ],
            ]],
        ]);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        try {
            $result = (new PrepareOkbProductMappingService(
                new SemicolonCsvReader(),
                new OkbProductApiClient($httpClient, new OkbProductResponseNormalizer(), 'https://okb.example'),
            ))->execute($directory.'/source.csv', $directory.'/snapshot', $directory.'/output', null);

            self::assertSame(1, $result->products);
            self::assertSame(0, $result->attributes);
            self::assertSame(0, $result->failures);
            self::assertSame("product_number;ean;reason\n", file_get_contents($directory.'/output/okb-product-mapping-failures.csv'));
            self::assertStringContainsString('4260454043503;4260454043503;Sofas;category-1;group-1', (string) file_get_contents($directory.'/output/okb-product-mapping.csv'));
        } finally {
            $files = glob($directory.'/*/*');
            if (false !== $files) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            $paths = glob($directory.'/*');
            if (false !== $paths) {
                foreach ($paths as $path) {
                    is_dir($path) ? rmdir($path) : unlink($path);
                }
            }
            rmdir($directory);
        }
    }
}
