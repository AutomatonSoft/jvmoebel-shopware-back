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
    public function testItCountsAnInvalidSourceRowTowardsTheLimit(): void
    {
        $directory = sys_get_temp_dir().'/jv-okb-product-mapping-'.bin2hex(random_bytes(8));
        mkdir($directory.'/snapshot', 0775, true);
        mkdir($directory.'/output', 0775, true);
        file_put_contents($directory.'/source.csv', "product_number;ean\ninvalid;not-an-ean\n4260454043503;4260454043503\n");
        file_put_contents($directory.'/snapshot/okb-categories.csv', "category_group_id;category_id;category_name\ngroup-1;category-1;Sofas\n");
        file_put_contents($directory.'/snapshot/okb-attributes.csv', "category_group_id;attribute_id;attribute_name\ngroup-1;color;Color\n");
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::never())->method('request');

        try {
            $result = (new PrepareOkbProductMappingService(
                new SemicolonCsvReader(),
                new OkbProductApiClient($httpClient, new OkbProductResponseNormalizer(), 'https://okb.example'),
            ))->execute($directory.'/source.csv', $directory.'/snapshot', $directory.'/output', 1);

            self::assertSame(0, $result->products);
            self::assertSame(1, $result->failures);
            self::assertSame("product_number;ean;reason\ninvalid;not-an-ean;\"EAN must contain exactly 13 digits.\"\n", file_get_contents($directory.'/output/okb-product-mapping-failures.csv'));
            self::assertSame("product_number;ean;category_name;category_id;category_group_id;standard_price_amount;suggested_retail_price_amount;currency\n", file_get_contents($directory.'/output/okb-product-mapping.csv'));
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
        foreach (['okb-product-mapping.csv', 'okb-product-attributes.csv', 'okb-product-mapping-failures.csv'] as $file) {
            file_put_contents($directory.'/output/'.$file, 'previous '.$file);
        }
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
            self::assertSame("product_number;ean;attribute_name;values_json\n", file_get_contents($directory.'/output/okb-product-attributes.csv'));
            self::assertSame([], false !== glob($directory.'/output/*.tmp.*') ? glob($directory.'/output/*.tmp.*') : []);
            self::assertSame([], false !== glob($directory.'/output/*.backup.*') ? glob($directory.'/output/*.backup.*') : []);
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

    public function testTheSourceVariationLeadsTheFamilyEvenWhenTheFamilyResponseOmitsIt(): void
    {
        $source = $this->okbVariation('4260454043503', '4260454043503', 'Sofas', 'Braun');

        $rows = $this->mappingRows($source, [
            $this->okbVariation('4260454043510', '4260454043503', 'Sofas', 'Beige'),
            $this->okbVariation('4260454043497', '4260454043503', 'Sofas', 'Schwarz'),
        ]);

        self::assertSame(['4260454043503', '4260454043497', '4260454043510'], array_column($rows, 1));
    }

    public function testAVariationOfAnotherProductOrCategoryIsNotImportedIntoTheFamily(): void
    {
        $source = $this->okbVariation('4260454043503', '4260454043503', 'Sofas', 'Braun');

        $rows = $this->mappingRows($source, [
            $source,
            $this->okbVariation('4260454043510', '4260454043503', 'Sofas', 'Beige'),
            $this->okbVariation('4260454043527', '4260454099999', 'Sofas', 'Grau'),
            $this->okbVariation('4260454043534', '4260454043503', 'Tische', 'Weiss'),
        ]);

        self::assertSame(['4260454043503', '4260454043510'], array_column($rows, 1));
    }

    /**
     * @param array<string, mixed>       $source
     * @param list<array<string, mixed>> $family
     *
     * @return list<list<string>>
     */
    private function mappingRows(array $source, array $family): array
    {
        $directory = sys_get_temp_dir().'/jv-okb-product-mapping-'.bin2hex(random_bytes(8));
        mkdir($directory.'/snapshot', 0775, true);
        mkdir($directory.'/output', 0775, true);
        file_put_contents($directory.'/source.csv', "product_number;ean\n4260454043503;4260454043503\n");
        file_put_contents($directory.'/snapshot/okb-categories.csv', "category_group_id;category_id;category_name\ngroup-1;category-1;Sofas\ngroup-2;category-2;Tische\n");
        file_put_contents($directory.'/snapshot/okb-attributes.csv', "category_group_id;attribute_id;attribute_name;feature_relevance\ngroup-1;color;Farbe;VARIATION_THEME\ngroup-2;color;Farbe;VARIATION_THEME\n");
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturnCallback(function (string $method, string $url, array $options) use ($source, $family): ResponseInterface {
            $response = $this->createMock(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn(200);
            $response->method('toArray')->willReturn(['productVariations' => isset($options['query']['sku']) ? [$source] : $family]);

            return $response;
        });

        try {
            (new PrepareOkbProductMappingService(
                new SemicolonCsvReader(),
                new OkbProductApiClient($httpClient, new OkbProductResponseNormalizer(), 'https://okb.example'),
            ))->execute($directory.'/source.csv', $directory.'/snapshot', $directory.'/output', null);

            $lines = array_values(array_filter(explode("\n", (string) file_get_contents($directory.'/output/okb-product-mapping.csv'))));

            return array_map(static fn (string $line): array => str_getcsv($line, ';', '"', '\\'), array_slice($lines, 1));
        } finally {
            foreach (glob($directory.'/*/*') ?: [] as $file) {
                unlink($file);
            }
            foreach (glob($directory.'/*') ?: [] as $path) {
                is_dir($path) ? rmdir($path) : unlink($path);
            }
            rmdir($directory);
        }
    }

    /** @return array<string, mixed> */
    private function okbVariation(string $ean, string $productReference, string $category, string $color): array
    {
        return [
            'productReference' => $productReference,
            'sku' => $ean,
            'ean' => $ean,
            'productDescription' => ['category' => $category, 'attributes' => [['name' => 'Farbe', 'values' => [$color]]]],
            'pricing' => ['standardPrice' => ['amount' => 999, 'currency' => 'EUR']],
        ];
    }
}
