<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\Catalog;

use Jv\Import\Integration\Okb\OkbCatalogSchemaApiClient;
use Jv\Import\Service\Catalog\CollectOkbSchemaSnapshotService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CollectOkbSchemaSnapshotServiceTest extends TestCase
{
    public function testItWritesApiDerivedSnapshotFilesAndRecordsAttributeFailures(): void
    {
        $directory = sys_get_temp_dir().'/jv-okb-schema-'.bin2hex(random_bytes(8));
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturnCallback(function (string $method, string $url, array $options): ResponseInterface {
            self::assertSame('GET', $method);
            $query = $options['query'];
            if ('https://okb.example/extermal/categories' === $url) {
                return $this->response(0 === $query['page'] ? [
                    'categories' => [
                        ['category_group' => 'Sofas', 'category_group_id' => 2, 'categoryId' => 20, 'name' => 'Sofas'],
                        ['category_group' => 'Beds', 'category_group_id' => 1, 'categoryId' => 10, 'name' => 'Beds'],
                        ['category_group' => 'Sofas', 'category_group_id' => 2, 'categoryId' => 19, 'name' => 'Sofa covers'],
                    ],
                ] : ['categories' => []]);
            }
            if ('https://okb.example/extermal/attributes' === $url && 10 === $query['categoryId']) {
                return $this->response([], 400);
            }
            if ('https://okb.example/extermal/attributes' === $url && 20 === $query['categoryId']) {
                return $this->response(['attributes' => [[
                    'attributeId' => 100,
                    'attributeKey' => 'color',
                    'name' => 'Color',
                    'type' => 'STRING',
                    'attributeGroup' => 'Appearance',
                    'description' => 'A color.',
                    'relevance' => 'HIGH',
                    'multiValue' => true,
                    'unit' => '',
                    'unitDisplayName' => null,
                    'featureRelevance' => ['VARIATION_THEME', 'FILTER'],
                    'allowedValues' => ['black', 'white'],
                ]]]);
            }

            self::fail(sprintf('Unexpected OKB request %s.', $url));
        });

        try {
            $result = (new CollectOkbSchemaSnapshotService(new OkbCatalogSchemaApiClient($httpClient, 'https://okb.example')))->execute($directory.'/snapshot');

            self::assertSame(2, $result->categoryGroups);
            self::assertSame(3, $result->categories);
            self::assertSame(1, $result->attributes);
            self::assertSame(2, $result->allowedValues);
            self::assertSame(1, $result->attributeFailures);
            self::assertSame("\xEF\xBB\xBFcategory_group_id;category_group;category_count;attribute_source_category_id\n1;Beds;1;10\n2;Sofas;2;20\n", file_get_contents($directory.'/snapshot/okb-category-groups.csv'));
            self::assertStringContainsString("1;Beds;10;Beds\n", (string) file_get_contents($directory.'/snapshot/okb-categories.csv'));
            self::assertStringContainsString("2;Sofas;19;\"Sofa covers\"\n", (string) file_get_contents($directory.'/snapshot/okb-categories.csv'));
            self::assertStringContainsString("2;Sofas;20;100;color;Appearance;Color;STRING;HIGH;true;;;VARIATION_THEME|FILTER;\"A color.\"\n", (string) file_get_contents($directory.'/snapshot/okb-attributes.csv'));
            self::assertStringContainsString("2;Sofas;20;100;color;Color;1;black\n", (string) file_get_contents($directory.'/snapshot/okb-attribute-allowed-values.csv'));
            self::assertStringContainsString('HTTP 400.', (string) file_get_contents($directory.'/snapshot/okb-attribute-fetch-failures.csv'));
            self::assertSame([], $this->temporaryFiles($directory.'/snapshot'));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testItDoesNotReplaceAnExistingSnapshotWhenCategoryPaginationIsInvalid(): void
    {
        $directory = sys_get_temp_dir().'/jv-okb-schema-'.bin2hex(random_bytes(8));
        mkdir($directory.'/snapshot', 0775, true);
        foreach (['okb-category-groups.csv', 'okb-categories.csv', 'okb-attributes.csv', 'okb-attribute-allowed-values.csv', 'okb-attribute-fetch-failures.csv'] as $file) {
            file_put_contents($directory.'/snapshot/'.$file, 'previous '.$file);
        }
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturnCallback(function (string $method, string $url, array $options): ResponseInterface {
            self::assertSame('https://okb.example/extermal/categories', $url);

            return $this->response([
                'categories' => 0 === $options['query']['page']
                    ? [['category_group' => 'Sofas', 'category_group_id' => 2, 'categoryId' => 20, 'name' => 'Sofas']]
                    : [['category_group' => 'Sofas', 'category_group_id' => 2, 'categoryId' => 20, 'name' => 'Sofas']],
            ]);
        });

        try {
            $this->expectException(\InvalidArgumentException::class);
            (new CollectOkbSchemaSnapshotService(new OkbCatalogSchemaApiClient($httpClient, 'https://okb.example')))->execute($directory.'/snapshot');
        } finally {
            foreach (['okb-category-groups.csv', 'okb-categories.csv', 'okb-attributes.csv', 'okb-attribute-allowed-values.csv', 'okb-attribute-fetch-failures.csv'] as $file) {
                self::assertSame('previous '.$file, file_get_contents($directory.'/snapshot/'.$file));
            }
            $this->removeDirectory($directory);
        }
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload, int $status = 200): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        if (200 === $status) {
            $response->method('toArray')->with(false)->willReturn($payload);
        }

        return $response;
    }

    /** @return list<string> */
    private function temporaryFiles(string $directory): array
    {
        $files = glob($directory.'/*.{tmp,backup}.*', \GLOB_BRACE);

        return false === $files ? [] : $files;
    }

    private function removeDirectory(string $directory): void
    {
        $files = glob($directory.'/*/*');
        if (false !== $files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        $directories = glob($directory.'/*');
        if (false !== $directories) {
            foreach ($directories as $path) {
                if (is_dir($path)) {
                    rmdir($path);
                }
            }
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
