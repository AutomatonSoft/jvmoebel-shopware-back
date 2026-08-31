<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\AfterCool;

use Jv\Import\Integration\AfterCool\AfterCoolResponseNormalizer;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolListerProductMapper;
use Jv\Import\Service\AfterCool\BuildAfterCoolShopwareProductRecordService;
use Jv\Import\Service\AfterCool\Exception\AfterCoolProductWriteValidationException;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class BuildAfterCoolShopwareProductRecordServiceTest extends TestCase
{
    public function testItBuildsANewInactiveProductWithoutOkbOwnedRelations(): void
    {
        $product = $this->mappedProduct();
        $taxId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $languageId = Uuid::randomHex();

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
        ]], $record['price']);
        self::assertSame([[
            'languageId' => $languageId,
            'name' => 'Sanitised Aftercool sofa',
        ]], $record['translations']);
        foreach (['categories', 'properties', 'configuratorSettings', 'children', 'visibilities', 'media', 'coverId'] as $foreignField) {
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
        foreach (['categories', 'properties', 'configuratorSettings', 'children', 'visibilities', 'media', 'coverId'] as $foreignField) {
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

    private function mappedProduct(int $index = 0, ?string $price = null): object
    {
        $contents = file_get_contents(__DIR__.'/../../../Fixtures/AfterCool/products-page-0.json');
        self::assertIsString($contents);
        $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (null !== $price) {
            $payload['items'][$index]['row']['Startpreis'] = $price;
        }
        $page = (new AfterCoolResponseNormalizer())->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);

        return (new AfterCoolListerProductMapper())->map($page->items[$index]);
    }
}
