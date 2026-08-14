<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\Validation;

use Jv\Import\Service\ProductImport\Dto\CosmoShopProductImportData;
use Jv\Import\Service\ProductImport\Exception\InvalidCosmoShopProductImportDataException;
use Jv\Import\Service\ProductImport\Validation\CosmoShopProductImportDataValidator;
use PHPUnit\Framework\TestCase;

final class CosmoShopProductImportDataValidatorTest extends TestCase
{
    public function testItAcceptsExactlyThirteenEanDigits(): void
    {
        (new CosmoShopProductImportDataValidator())->validate($this->data('4260174423463'));

        self::addToAssertionCount(1);
    }

    public function testItRejectsAnEanWithLettersOrTheWrongLength(): void
    {
        $validator = new CosmoShopProductImportDataValidator();

        foreach (['426017442346', '42601744234630', '42601744234AB'] as $ean) {
            try {
                $validator->validate($this->data($ean));
                self::fail(sprintf('Expected EAN %s to be rejected.', $ean));
            } catch (InvalidCosmoShopProductImportDataException $exception) {
                self::assertSame('CosmoShop EAN must contain exactly 13 digits.', $exception->getMessage());
            }
        }
    }

    private function data(string $ean): CosmoShopProductImportData
    {
        return new CosmoShopProductImportData(
            productNumber: '4260174423463',
            sourceInactive: '0',
            ean: $ean,
            stock: '1',
            priceGross: '119.00',
            minPurchase: '1',
            maxPurchase: null,
            weight: '0',
            length: '0',
            width: '0',
            height: '0',
            contents: '1',
            referenceUnit: '1',
            packUnit: null,
            manufacturerName: null,
            deliveryTimeId: null,
            unitId: null,
            listPriceGross: null,
            description: null,
            seoPath: null,
            mappedRecord: [],
        );
    }
}
