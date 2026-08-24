<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Jv\Import\Integration\Csv\SemicolonCsvReader;

final readonly class PrepareCatalogShopwareImportCsvService
{
    public function __construct(private SemicolonCsvReader $csvReader)
    {
    }

    /** Returns the number of written Shopware product records. */
    public function execute(string $productsFile, string $attributesFile, string $outputFile): int
    {
        $output = fopen($outputFile, 'wb');
        if (false === $output) {
            throw new \InvalidArgumentException(sprintf('Shopware import CSV "%s" cannot be written.', $outputFile));
        }

        $attributes = $this->csvReader->rows($attributesFile, ['product_number', 'ean', 'attribute_name', 'values_json']);
        $attributes->rewind();
        $hasAttribute = $attributes->valid();
        $written = 0;

        try {
            $this->write($output, ['record_type', 'product_number', 'ean', 'category_id', 'category_group_id', 'standard_price_amount', 'currency', 'attributes_json']);
            foreach ($this->csvReader->rows($productsFile, ['product_number', 'ean', 'category_id', 'category_group_id', 'standard_price_amount', 'currency']) as $line => $product) {
                $productNumber = $this->required($product, 'product_number', $productsFile, $line);
                $ean = $this->required($product, 'ean', $productsFile, $line);
                $productAttributes = [];
                while ($hasAttribute) {
                    /** @var array<string, string> $attribute */
                    $attribute = $attributes->current();
                    if ($attribute['product_number'] !== $productNumber) {
                        break;
                    }
                    if ($attribute['ean'] !== $ean) {
                        throw new \InvalidArgumentException(sprintf('Attribute EAN does not match product number "%s".', $productNumber));
                    }
                    $productAttributes[] = [$attribute['attribute_name'], $this->values($attribute['values_json'], $attributesFile, $attributes->key())];
                    $attributes->next();
                    $hasAttribute = $attributes->valid();
                }
                $row = [$productNumber, $ean, $this->required($product, 'category_id', $productsFile, $line), $this->required($product, 'category_group_id', $productsFile, $line), $product['standard_price_amount'], $product['currency'], json_encode($productAttributes, \JSON_THROW_ON_ERROR)];
                $this->write($output, ['parent', ...$row]);
                $this->write($output, ['child', ...$row]);
                $written += 2;
            }
            if ($hasAttribute) {
                /** @var array<string, string> $attribute */
                $attribute = $attributes->current();
                throw new \InvalidArgumentException(sprintf('Attribute row references unknown product number "%s" or is out of product order.', $attribute['product_number']));
            }
        } finally {
            fclose($output);
        }

        return $written;
    }

    /** @param array<string, string> $row */
    private function required(array $row, string $field, string $file, int $line): string
    {
        if ('' === $row[$field]) {
            throw new \InvalidArgumentException(sprintf('CSV file "%s" has empty "%s" on line %d.', $file, $field, $line));
        }

        return $row[$field];
    }

    /** @return list<string> */
    private function values(string $json, string $file, int $line): array
    {
        try {
            $values = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException(sprintf('CSV file "%s" has invalid values_json on line %d.', $file, $line));
        }
        if (!is_array($values) || !array_is_list($values) || [] !== array_filter($values, static fn (mixed $value): bool => !is_string($value))) {
            throw new \InvalidArgumentException(sprintf('CSV file "%s" values_json must contain only strings on line %d.', $file, $line));
        }

        return $values;
    }

    /** @param resource $handle
     * @param list<string> $row
     */
    private function write($handle, array $row): void
    {
        if (false === fputcsv($handle, $row, ';', '"', '\\')) {
            throw new \RuntimeException('Shopware import CSV output cannot be written.');
        }
    }
}
