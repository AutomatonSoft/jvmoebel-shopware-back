<?php declare(strict_types=1);

namespace Jv\CatalogImport\Integration\CosmoShop\Normalizer;

use Jv\CatalogImport\Service\ProductImport\Dto\CosmoShopProductImportData;

final class CosmoShopProductImportDataNormalizer
{
    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $mappedRecord
     */
    public function normalize(array $row, array $mappedRecord): CosmoShopProductImportData
    {
        return new CosmoShopProductImportData(
            $this->value($mappedRecord, 'productNumber'),
            $this->value($row, 'source_inactive'),
            $this->value($row, 'stock'),
            $this->value($row, 'price_gross'),
            $this->nullableValue($row, 'min_purchase'),
            $this->nullableValue($row, 'contents'),
            $this->nullableValue($row, 'reference_unit'),
            $this->nullableValue($row, 'pack_unit'),
            $this->nullableValue($row, 'manufacturer_name'),
            $this->nullableValue($row, 'delivery_time_id'),
            $this->nullableValue($row, 'unit_id'),
            $this->nullableValue($row, 'list_price_gross'),
            $this->nullableValue($row, 'description'),
            $this->nullableValue($row, 'urlkey'),
            $mappedRecord,
        );
    }

    /** @param array<string, mixed> $values */
    private function value(array $values, string $key): string
    {
        return trim((string) ($values[$key] ?? ''));
    }

    /** @param array<string, mixed> $values */
    private function nullableValue(array $values, string $key): ?string
    {
        $value = $this->value($values, $key);

        return '' === $value ? null : $value;
    }
}
