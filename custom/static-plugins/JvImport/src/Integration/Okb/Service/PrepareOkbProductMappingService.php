<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb\Service;

use Jv\Import\Integration\Okb\Dto\OkbProductMappingPreparationResult;
use Jv\Import\Integration\Okb\Dto\OkbProductVariation;
use Jv\Import\Integration\Okb\OkbCsvReader;
use Jv\Import\Integration\Okb\OkbProductApiClient;

final readonly class PrepareOkbProductMappingService
{
    public function __construct(
        private OkbCsvReader $csvReader,
        private OkbProductApiClient $apiClient,
    ) {
    }

    public function execute(string $sourceCsv, string $snapshotDirectory, string $outputDirectory, ?int $limit): OkbProductMappingPreparationResult
    {
        if (null !== $limit && $limit <= 0) {
            throw new \InvalidArgumentException('--limit must be greater than zero.');
        }
        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
            throw new \InvalidArgumentException(sprintf('Output directory "%s" cannot be created.', $outputDirectory));
        }

        $categories = $this->categoryLookup($snapshotDirectory);
        $products = $this->openOutput($outputDirectory.'/okb-product-mapping.csv', [
            'product_number', 'ean', 'product_reference', 'category_name', 'category_id', 'category_group_id', 'standard_price_amount', 'currency',
        ]);
        $attributes = $this->openOutput($outputDirectory.'/okb-product-attributes.csv', ['product_number', 'ean', 'attribute_name', 'values_json']);
        $failures = $this->openOutput($outputDirectory.'/okb-product-mapping-failures.csv', ['product_number', 'ean', 'reason']);
        $productCount = 0;
        $attributeCount = 0;
        $failureCount = 0;

        try {
            foreach ($this->csvReader->rows($sourceCsv, ['product_number', 'ean']) as $row) {
                $productNumber = $row['product_number'];
                $ean = $row['ean'];
                try {
                    if (!preg_match('/^\d{13}$/D', $ean)) {
                        throw new \InvalidArgumentException('EAN must contain exactly 13 digits.');
                    }
                    $variation = $this->apiClient->findByEan($ean);
                    $category = $categories[$variation->categoryName] ?? null;
                    if (null === $category) {
                        throw new \InvalidArgumentException(sprintf('OKB category "%s" is missing from the supplied snapshot.', $variation->categoryName));
                    }
                    $this->writeVariation($products, $productNumber, $variation, $category);
                    foreach ($variation->attributes as $attribute) {
                        $this->write($attributes, [$productNumber, $ean, $attribute->name, json_encode($attribute->values, \JSON_THROW_ON_ERROR)]);
                        ++$attributeCount;
                    }
                    ++$productCount;
                } catch (\Throwable $exception) {
                    $this->write($failures, [$productNumber, $ean, $exception->getMessage()]);
                    ++$failureCount;
                }
                if (null !== $limit && $limit <= $productCount + $failureCount) {
                    break;
                }
            }
        } finally {
            fclose($products);
            fclose($attributes);
            fclose($failures);
        }

        return new OkbProductMappingPreparationResult($productCount, $attributeCount, $failureCount);
    }

    /** @return array<string, array{categoryId: string, categoryGroupId: string}> */
    private function categoryLookup(string $snapshotDirectory): array
    {
        $categories = [];
        foreach ($this->csvReader->rows(rtrim($snapshotDirectory, '/').'/okb-categories.csv', ['category_group_id', 'category_id', 'category_name']) as $row) {
            if (isset($categories[$row['category_name']])) {
                throw new \InvalidArgumentException(sprintf('OKB snapshot category "%s" is not unique.', $row['category_name']));
            }
            $categories[$row['category_name']] = ['categoryId' => $row['category_id'], 'categoryGroupId' => $row['category_group_id']];
        }

        return $categories;
    }

    /**
     * @param resource                                           $handle
     * @param array{categoryId: string, categoryGroupId: string} $category
     */
    private function writeVariation($handle, string $productNumber, OkbProductVariation $variation, array $category): void
    {
        $this->write($handle, [
            $productNumber,
            $variation->ean,
            $variation->productReference,
            $variation->categoryName,
            $category['categoryId'],
            $category['categoryGroupId'],
            null === $variation->standardPriceAmount ? '' : (string) $variation->standardPriceAmount,
            $variation->currency ?? '',
        ]);
    }

    /** @param list<string> $headers
     * @return resource
     */
    private function openOutput(string $file, array $headers)
    {
        $handle = fopen($file, 'wb');
        if (false === $handle) {
            throw new \InvalidArgumentException(sprintf('Output file "%s" cannot be written.', $file));
        }
        $this->write($handle, $headers);

        return $handle;
    }

    /**
     * @param resource     $handle
     * @param list<string> $row
     */
    private function write($handle, array $row): void
    {
        if (false === fputcsv($handle, $row, ';', '"', '\\')) {
            throw new \RuntimeException('OKB product mapping output cannot be written.');
        }
    }
}
