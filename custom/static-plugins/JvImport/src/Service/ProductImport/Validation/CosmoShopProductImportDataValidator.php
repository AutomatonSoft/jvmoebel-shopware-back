<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Validation;

use Jv\Import\Service\ProductImport\Dto\CosmoShopProductImportData;
use Jv\Import\Service\ProductImport\Exception\InvalidCosmoShopProductImportDataException;

final class CosmoShopProductImportDataValidator
{
    public function validate(CosmoShopProductImportData $data): void
    {
        if ('' === $data->productNumber) {
            throw new InvalidCosmoShopProductImportDataException('CosmoShop product record is missing a product number.');
        }
        if (!ctype_digit($data->stock)) {
            throw new InvalidCosmoShopProductImportDataException('CosmoShop stock must be a non-negative integer.');
        }
        if ((float) $data->priceGross <= 0) {
            throw new InvalidCosmoShopProductImportDataException('CosmoShop price_gross must be positive.');
        }
        if (null !== $data->description && str_contains($data->description, chr(92).'"')) {
            throw new InvalidCosmoShopProductImportDataException('CosmoShop description contains CSV escape sequences; regenerate the import file.');
        }
    }
}
