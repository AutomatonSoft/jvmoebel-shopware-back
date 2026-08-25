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
        if (!preg_match('/^\d{13}$/D', $data->ean)) {
            throw new InvalidCosmoShopProductImportDataException('CosmoShop EAN must contain exactly 13 digits.');
        }
        if (!in_array($data->sourceInactive, ['0', '1'], true)) {
            throw new InvalidCosmoShopProductImportDataException('CosmoShop source_inactive must be 0 or 1.');
        }
        if (!ctype_digit($data->stock)) {
            throw new InvalidCosmoShopProductImportDataException('CosmoShop stock must be a non-negative integer.');
        }
        $this->positiveDecimal($data->priceGross, 'price_gross');
        $this->positiveInteger($data->minPurchase, 'min_purchase');
        if (null !== $data->maxPurchase && !$this->isZero($data->maxPurchase)) {
            $this->positiveInteger($data->maxPurchase, 'max_purchase');
            if ((int) $data->maxPurchase < (int) $data->minPurchase) {
                throw new InvalidCosmoShopProductImportDataException('CosmoShop max_purchase must not be lower than min_purchase.');
            }
        }
        foreach (['weight' => $data->weight, 'length' => $data->length, 'width' => $data->width, 'height' => $data->height] as $field => $value) {
            $this->nonNegativeDecimal($value, $field);
        }
        $this->positiveDecimal($data->contents, 'contents');
        $this->positiveDecimal($data->referenceUnit, 'reference_unit');
        foreach (['delivery_time_id' => $data->deliveryTimeId, 'unit_id' => $data->unitId] as $field => $value) {
            if (null !== $value && !ctype_digit($value)) {
                throw new InvalidCosmoShopProductImportDataException(sprintf('CosmoShop %s must be a non-negative integer.', $field));
            }
        }
        if (null !== $data->listPriceGross && !$this->isZero($data->listPriceGross)) {
            $this->positiveDecimal($data->listPriceGross, 'list_price_gross');
        }
        if (null !== $data->description && str_contains($data->description, chr(92).'"')) {
            throw new InvalidCosmoShopProductImportDataException('CosmoShop description contains CSV escape sequences; regenerate the import file.');
        }
    }

    private function positiveInteger(?string $value, string $field): void
    {
        if (null === $value || !ctype_digit($value) || 0 === (int) $value) {
            throw new InvalidCosmoShopProductImportDataException(sprintf('CosmoShop %s must be an integer greater than zero.', $field));
        }
    }

    private function positiveDecimal(?string $value, string $field): void
    {
        if (null === $value || !preg_match('/^\d+(?:\.\d+)?$/', $value) || (float) $value <= 0) {
            throw new InvalidCosmoShopProductImportDataException(sprintf('CosmoShop %s must be a positive decimal number.', $field));
        }
    }

    private function nonNegativeDecimal(?string $value, string $field): void
    {
        if (null === $value || !preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidCosmoShopProductImportDataException(sprintf('CosmoShop %s must be a non-negative decimal number.', $field));
        }
    }

    private function isZero(string $value): bool
    {
        return (bool) preg_match('/^0+(?:\.0+)?$/', $value);
    }
}
