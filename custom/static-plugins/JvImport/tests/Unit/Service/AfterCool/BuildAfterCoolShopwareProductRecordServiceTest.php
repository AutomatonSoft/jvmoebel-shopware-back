<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\AfterCool;

use Jv\Import\Integration\AfterCool\AfterCoolResponseNormalizer;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolListerProductMapper;
use Jv\Import\Service\AfterCool\Dto\AfterCoolMappedProduct;
use Jv\Import\Service\AfterCool\Exception\AfterCoolProductWriteValidationException;
use Jv\Import\Service\AfterCool\Import\BuildAfterCoolShopwareProductRecordService;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;

final class BuildAfterCoolShopwareProductRecordServiceTest extends TestCase
{
    public function testItBuildsANewInactiveProductWithoutOkbOwnedRelations(): void
    {
        $product = $this->mappedProduct();
        $taxId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $salesChannelId = Market::Germany->salesChannelId();

        $record = (new BuildAfterCoolShopwareProductRecordService())->build(
            $product,
            null,
            $taxId,
            19.0,
            $currencyId,
            $languageId,
        );

        self::assertSame(ProductImportIdentity::fromProductNumber('4260174423463'), $record['id']);
        self::assertSame('4260174423463', $record['productNumber']);
        self::assertSame('4260174423463', $record['ean']);
        self::assertSame('Sanitised Aftercool sofa', $record['name']);
        self::assertSame(7, $record['stock']);
        self::assertSame($taxId, $record['taxId']);
        self::assertSame(1, $record['minPurchase']);
        self::assertFalse($record['active']);
        self::assertSame([[
            'currencyId' => $currencyId,
            'gross' => 1199.0,
            'net' => 1007.56,
            'linked' => true,
            'listPrice' => [
                'gross' => 1498.75,
                'net' => 1259.45,
                'linked' => true,
            ],
        ]], $record['price']);
        self::assertSame([[
            'languageId' => $languageId,
            'name' => 'Sanitised Aftercool sofa',
        ]], $record['translations']);
        self::assertSame([
            'id' => Uuid::fromStringToHex('jvmoebel.product-manufacturer.cosmoshop.jvmoebel'),
            'name' => 'JVMOEBEL',
        ], $record['manufacturer']);
        self::assertSame([[
            'id' => Uuid::fromStringToHex('jvmoebel.product-visibility.'.$salesChannelId.$record['id']),
            'salesChannelId' => $salesChannelId,
            'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
        ]], $record['visibilities']);
        foreach (['categories', 'properties', 'configuratorSettings', 'children', 'media', 'coverId'] as $foreignField) {
            self::assertArrayNotHasKey($foreignField, $record);
        }
    }

    public function testAnExistingProductUpdateContainsOnlyAftercoolOwnedFields(): void
    {
        $existingProductId = Uuid::randomHex();
        $record = (new BuildAfterCoolShopwareProductRecordService())->build(
            $this->mappedProduct(),
            $existingProductId,
            Uuid::randomHex(),
            19.0,
            Uuid::randomHex(),
            Uuid::randomHex(),
        );

        self::assertSame($existingProductId, $record['id']);
        self::assertArrayNotHasKey('active', $record, 'Aftercool must not republish or deactivate an existing product.');
        self::assertArrayNotHasKey('taxId', $record, 'An existing product keeps tax ownership outside this import.');
        self::assertArrayNotHasKey('minPurchase', $record);
        self::assertArrayNotHasKey('name', $record, 'Existing translations are updated only through the German language row.');
        self::assertArrayNotHasKey('description', $record, 'A placeholder description must preserve the existing German description.');
        self::assertCount(1, $record['price'], 'The payload must not echo or replace prices of other currencies.');
        self::assertCount(1, $record['translations'], 'The payload must not echo or replace other language translations.');
        self::assertSame('JVMOEBEL', $record['manufacturer']['name']);
        self::assertSame(Market::Germany->salesChannelId(), $record['visibilities'][0]['salesChannelId']);
        foreach (['categories', 'properties', 'configuratorSettings', 'children', 'media', 'coverId'] as $foreignField) {
            self::assertArrayNotHasKey($foreignField, $record);
        }
    }

    public function testZeroPriceCannotCreateANewProductButDoesNotBlockAStockUpdate(): void
    {
        $product = $this->mappedProduct(price: '0');
        $service = new BuildAfterCoolShopwareProductRecordService();
        $taxId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $languageId = Uuid::randomHex();

        try {
            $service->build($product, null, $taxId, 19.0, $currencyId, $languageId);
            self::fail('A new Shopware product requires a positive price.');
        } catch (AfterCoolProductWriteValidationException $exception) {
            self::assertSame('invalid_price', $exception->safeCode());
        }

        $record = $service->build($product, Uuid::randomHex(), $taxId, 19.0, $currencyId, $languageId);
        self::assertSame(7, $record['stock']);
        self::assertArrayNotHasKey('price', $record, 'An unusable source price must preserve the existing EUR price.');
    }

    #[DataProvider('uvpProvider')]
    public function testItAppliesTheConfirmedUvpFormulaLiterally(string $price, float $expectedUvp): void
    {
        $currencyId = Uuid::randomHex();
        $record = (new BuildAfterCoolShopwareProductRecordService())->build(
            $this->mappedProduct(price: $price),
            null,
            Uuid::randomHex(),
            19.0,
            $currencyId,
            Uuid::randomHex(),
        );

        self::assertSame($expectedUvp, $record['price'][0]['listPrice']['gross']);
        self::assertSame(round($expectedUvp / 1.19, 2), $record['price'][0]['listPrice']['net']);
        self::assertTrue($record['price'][0]['listPrice']['linked']);
    }

    /** @return iterable<string, array{string, float}> */
    public static function uvpProvider(): iterable
    {
        yield 'below 1000' => ['999', 1348.65];
        yield 'exactly 1000' => ['1000', 1250.0];
        yield 'exactly 2499' => ['2499', 3123.75];
        yield 'exactly 2500' => ['2500', 2950.0];
        yield 'exactly 4999' => ['4999', 5898.82];
        yield 'gap above 4999' => ['4999.5', 6749.33];
        yield 'exactly 5000 follows the final branch' => ['5000', 6750.0];
        yield 'above 5000' => ['5000.01', 5500.01];
    }

    public function testItPreservesOtherCurrencyListAndRegulationPrices(): void
    {
        $eurId = Uuid::randomHex();
        $otherCurrencyId = Uuid::randomHex();
        $record = (new BuildAfterCoolShopwareProductRecordService())->build(
            $this->mappedProduct(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            19.0,
            $eurId,
            Uuid::randomHex(),
            [[
                'currencyId' => $otherCurrencyId,
                'gross' => 777.0,
                'net' => 700.0,
                'linked' => false,
                'listPrice' => ['gross' => 888.0, 'net' => 800.0, 'linked' => false],
                'regulationPrice' => ['gross' => 666.0, 'net' => 600.0, 'linked' => false],
            ]],
        );

        $byCurrency = array_column($record['price'], null, 'currencyId');
        self::assertSame(888.0, $byCurrency[$otherCurrencyId]['listPrice']['gross']);
        self::assertSame(666.0, $byCurrency[$otherCurrencyId]['regulationPrice']['gross']);
        self::assertSame(1498.75, $byCurrency[$eurId]['listPrice']['gross']);
    }

    private function mappedProduct(int $index = 0, ?string $price = null): object
    {
        $contents = file_get_contents(__DIR__.'/../../../Fixtures/AfterCool/products-page-0.json');
        self::assertIsString($contents);
        $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (null !== $price) {
            $payload['items'][$index]['row']['Startpreis'] = $price;
        }
        $page = (new AfterCoolResponseNormalizer())->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);

        $mapped = (new AfterCoolListerProductMapper())->map($page->items[$index]);

        return new AfterCoolMappedProduct(
            $mapped->account,
            $mapped->dataset,
            $mapped->factoryId,
            $mapped->sourceProductId,
            $mapped->sourceArtikelnummer,
            $mapped->ean,
            $mapped->productNumber,
            $mapped->name,
            $mapped->rowNo,
            $mapped->grossPrice,
            $mapped->stock,
            $mapped->description,
            $mapped->mediaUrls,
            [],
        );
    }
}
