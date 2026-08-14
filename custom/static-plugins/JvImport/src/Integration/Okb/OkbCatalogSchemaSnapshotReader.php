<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb;

use Jv\Import\Service\Catalog\Dto\CatalogAllowedValue;
use Jv\Import\Service\Catalog\Dto\CatalogAttribute;
use Jv\Import\Service\Catalog\Dto\CatalogCategory;
use Jv\Import\Service\Catalog\Dto\CatalogCategoryGroup;
use Jv\Import\Service\Catalog\Dto\CatalogSchemaSnapshot;

final readonly class OkbCatalogSchemaSnapshotReader
{
    private const string SOURCE_CODE = 'okb';

    public function __construct(private OkbCsvReader $csvReader)
    {
    }

    public function read(string $directory): CatalogSchemaSnapshot
    {
        $directory = rtrim($directory, '/');
        $this->assertComplete($directory);

        $groups = [];
        foreach ($this->csvReader->rows($directory.'/okb-category-groups.csv', ['category_group_id', 'category_group']) as $row) {
            $groups[] = new CatalogCategoryGroup($this->required($row, 'category_group_id', 'category group'), $this->required($row, 'category_group', 'category group'));
        }
        $categories = [];
        foreach ($this->csvReader->rows($directory.'/okb-categories.csv', ['category_group_id', 'category_id', 'category_name']) as $row) {
            $categories[] = new CatalogCategory(
                $this->required($row, 'category_id', 'category'),
                $this->required($row, 'category_group_id', 'category'),
                $this->required($row, 'category_name', 'category'),
            );
        }
        $attributes = [];
        foreach ($this->csvReader->rows($directory.'/okb-attributes.csv', ['category_group_id', 'attribute_id', 'attribute_name', 'attribute_type', 'multi_value', 'feature_relevance']) as $row) {
            $multiValue = match ($row['multi_value']) {
                'true' => true,
                'false' => false,
                default => throw new \InvalidArgumentException(sprintf('OKB attribute %s has invalid multi_value "%s".', $row['attribute_id'], $row['multi_value'])),
            };
            $attributes[] = new CatalogAttribute(
                $this->required($row, 'attribute_id', 'attribute'),
                $this->required($row, 'category_group_id', 'attribute'),
                $this->required($row, 'attribute_name', 'attribute'),
                $this->required($row, 'attribute_type', 'attribute'),
                '' === $row['feature_relevance'] ? null : $row['feature_relevance'],
                $multiValue,
                OkbAttributeStorage::fromFeatureRelevance($row['feature_relevance'])->value,
            );
        }
        $allowedValues = [];
        foreach ($this->csvReader->rows($directory.'/okb-attribute-allowed-values.csv', ['attribute_id', 'allowed_value_position', 'allowed_value']) as $row) {
            if (!ctype_digit($row['allowed_value_position'])) {
                throw new \InvalidArgumentException(sprintf('OKB attribute %s has invalid allowed_value_position "%s".', $row['attribute_id'], $row['allowed_value_position']));
            }
            $allowedValues[] = new CatalogAllowedValue(
                $this->required($row, 'attribute_id', 'allowed value'),
                (int) $row['allowed_value_position'],
                $this->required($row, 'allowed_value', 'allowed value'),
            );
        }

        return new CatalogSchemaSnapshot(self::SOURCE_CODE, $groups, $categories, $attributes, $allowedValues);
    }

    private function assertComplete(string $directory): void
    {
        foreach ($this->csvReader->rows($directory.'/okb-attribute-fetch-failures.csv', ['category_group_id', 'category_group', 'attribute_source_category_id', 'error']) as $row) {
            throw new \InvalidArgumentException(sprintf('OKB snapshot has an attribute-fetch failure for category group %s: %s.', $row['category_group_id'], $row['error']));
        }
    }

    /** @param array<string, string> $row */
    private function required(array $row, string $key, string $recordType): string
    {
        if ('' === $row[$key]) {
            throw new \InvalidArgumentException(sprintf('OKB %s record has an empty %s.', $recordType, $key));
        }

        return $row[$key];
    }
}
