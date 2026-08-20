<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Jv\Import\Integration\Csv\SemicolonCsvReader;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductAttribute;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductData;

final readonly class CatalogPreparedProductReader
{
    public function __construct(private SemicolonCsvReader $csvReader)
    {
    }

    /** @return list<CatalogProductData> */
    public function read(string $sourceCode, string $productsFile, string $attributesFile): array
    {
        if ('' === trim($sourceCode)) {
            throw new \InvalidArgumentException('Catalog source code must not be empty.');
        }

        $products = [];
        foreach ($this->csvReader->rows($productsFile, ['product_number', 'ean', 'category_id', 'category_group_id', 'standard_price_amount', 'currency']) as $line => $row) {
            $productNumber = $this->required($row, 'product_number', $productsFile, $line);
            $ean = $this->required($row, 'ean', $productsFile, $line);
            $key = $this->productKey($productNumber);
            $current = $products[$key] ?? null;
            if (null !== $current && $current['ean'] !== $ean) {
                throw new \InvalidArgumentException(sprintf('Product number "%s" has several EAN values in "%s".', $productNumber, $productsFile));
            }
            $products[$key] = [
                'productNumber' => $productNumber,
                'ean' => $ean,
                'categoryId' => $this->required($row, 'category_id', $productsFile, $line),
                'categoryGroupId' => $this->required($row, 'category_group_id', $productsFile, $line),
                'standardPriceAmount' => $this->price($row['standard_price_amount'], $productsFile, $line),
                'currency' => '' === $row['currency'] ? null : strtoupper($row['currency']),
            ];
        }

        $attributes = [];
        foreach ($this->csvReader->rows($attributesFile, ['product_number', 'ean', 'attribute_name', 'values_json']) as $line => $row) {
            $productNumber = $this->required($row, 'product_number', $attributesFile, $line);
            $key = $this->productKey($productNumber);
            $product = $products[$key] ?? null;
            if (null === $product) {
                throw new \InvalidArgumentException(sprintf('Attribute row on line %d references unknown product number "%s".', $line, $productNumber));
            }
            if ($product['ean'] !== $this->required($row, 'ean', $attributesFile, $line)) {
                throw new \InvalidArgumentException(sprintf('Attribute EAN does not match product number "%s".', $productNumber));
            }
            $name = $this->required($row, 'attribute_name', $attributesFile, $line);
            if (isset($attributes[$key][$name])) {
                throw new \InvalidArgumentException(sprintf('Product number "%s" has duplicate attribute "%s".', $productNumber, $name));
            }
            $attributes[$key][$name] = new CatalogProductAttribute($name, $this->attributeValues($row['values_json'], $attributesFile, $line));
        }

        $prepared = [];
        foreach ($products as $key => $product) {
            $prepared[] = new CatalogProductData(
                $sourceCode,
                $product['productNumber'],
                $product['ean'],
                $product['categoryId'],
                $product['categoryGroupId'],
                $product['standardPriceAmount'],
                $product['currency'],
                array_values($attributes[$key] ?? []),
            );
        }

        return $prepared;
    }

    /** @param array<string, string> $row */
    private function required(array $row, string $field, string $file, int $line): string
    {
        if ('' === $row[$field]) {
            throw new \InvalidArgumentException(sprintf('CSV file "%s" has empty "%s" on line %d.', $file, $field, $line));
        }

        return $row[$field];
    }

    private function price(string $value, string $file, int $line): ?float
    {
        if ('' === $value) {
            return null;
        }
        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException(sprintf('CSV file "%s" has invalid standard price on line %d.', $file, $line));
        }

        return (float) $normalized;
    }

    /** @return list<string> */
    private function attributeValues(string $value, string $file, int $line): array
    {
        try {
            $values = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException(sprintf('CSV file "%s" has invalid values_json on line %d.', $file, $line));
        }
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException(sprintf('CSV file "%s" values_json must be a list on line %d.', $file, $line));
        }
        foreach ($values as $attributeValue) {
            if (!is_string($attributeValue)) {
                throw new \InvalidArgumentException(sprintf('CSV file "%s" values_json must contain only strings on line %d.', $file, $line));
            }
        }

        return $values;
    }

    private function productKey(string $productNumber): string
    {
        return 'product-number:'.$productNumber;
    }
}
