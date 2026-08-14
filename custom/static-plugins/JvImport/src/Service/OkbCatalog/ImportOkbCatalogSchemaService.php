<?php declare(strict_types=1);

namespace Jv\Import\Service\OkbCatalog;

use Jv\Import\Core\Content\OkbCategoryGroupAttribute\OkbCategoryGroupAttributeCollection;
use Jv\Import\Integration\Okb\OkbAttributeStorage;
use Jv\Import\Integration\Okb\OkbCatalogIdentity;
use Jv\Import\Integration\Okb\OkbCsvReader;
use Jv\Import\Service\OkbCatalog\Dto\OkbCatalogSchemaImportResult;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

final readonly class ImportOkbCatalogSchemaService
{
    private const int BATCH_SIZE = 500;

    /**
     * @param EntityRepository<CategoryCollection>                  $categoryRepository
     * @param EntityRepository<PropertyGroupCollection>             $propertyGroupRepository
     * @param EntityRepository<PropertyGroupOptionCollection>       $propertyOptionRepository
     * @param EntityRepository<OkbCategoryGroupAttributeCollection> $categoryGroupAttributeRepository
     */
    public function __construct(
        private OkbCsvReader $csvReader,
        private EntityRepository $categoryRepository,
        private EntityRepository $propertyGroupRepository,
        private EntityRepository $propertyOptionRepository,
        private EntityRepository $categoryGroupAttributeRepository,
    ) {
    }

    public function execute(string $directory, bool $dryRun, Context $context, ?string $groupParentId = null): OkbCatalogSchemaImportResult
    {
        $directory = rtrim($directory, '/');
        $this->assertSnapshotIsComplete($directory);

        $categoryGroups = $this->importCategoryGroups($directory, $dryRun, $context, $groupParentId);
        $categories = $this->importCategories($directory, $dryRun, $context);
        [$attributeRelations, $propertyGroups, $propertyAttributes] = $this->importAttributeSchema($directory, $dryRun, $context);
        $propertyOptions = $this->importAllowedValues($directory, $propertyAttributes, $dryRun, $context);

        return new OkbCatalogSchemaImportResult(
            $categoryGroups,
            $categories,
            $attributeRelations,
            $propertyGroups,
            $propertyOptions,
        );
    }

    private function assertSnapshotIsComplete(string $directory): void
    {
        $failures = $this->csvReader->rows($directory.'/okb-attribute-fetch-failures.csv', [
            'category_group_id',
            'category_group',
            'attribute_source_category_id',
            'error',
        ]);
        foreach ($failures as $row) {
            throw new \InvalidArgumentException(sprintf('OKB snapshot has an attribute-fetch failure for category group %s: %s.', $row['category_group_id'], $row['error']));
        }
    }

    private function importCategoryGroups(string $directory, bool $dryRun, Context $context, ?string $groupParentId): int
    {
        $records = [];
        $count = 0;
        foreach ($this->csvReader->rows($directory.'/okb-category-groups.csv', ['category_group_id', 'category_group']) as $row) {
            $this->requireValues($row, ['category_group_id', 'category_group'], 'category group');
            $record = [
                'id' => OkbCatalogIdentity::categoryGroupId($row['category_group_id']),
                'name' => $row['category_group'],
                'active' => true,
                'visible' => true,
                'type' => CategoryDefinition::TYPE_PAGE,
            ];
            if (null !== $groupParentId) {
                $record['parentId'] = $groupParentId;
            }
            $records[] = $record;
            ++$count;
            $this->upsertBatch($this->categoryRepository, $records, $dryRun, $context);
        }
        $this->upsertRemaining($this->categoryRepository, $records, $dryRun, $context);

        return $count;
    }

    private function importCategories(string $directory, bool $dryRun, Context $context): int
    {
        $records = [];
        $count = 0;
        foreach ($this->csvReader->rows($directory.'/okb-categories.csv', ['category_group_id', 'category_id', 'category_name']) as $row) {
            $this->requireValues($row, ['category_group_id', 'category_id', 'category_name'], 'category');
            $records[] = [
                'id' => OkbCatalogIdentity::categoryId($row['category_id']),
                'parentId' => OkbCatalogIdentity::categoryGroupId($row['category_group_id']),
                'name' => $row['category_name'],
                'active' => true,
                'visible' => true,
                'type' => CategoryDefinition::TYPE_PAGE,
            ];
            ++$count;
            $this->upsertBatch($this->categoryRepository, $records, $dryRun, $context);
        }
        $this->upsertRemaining($this->categoryRepository, $records, $dryRun, $context);

        return $count;
    }

    /**
     * @return array{0: int, 1: int, 2: array<string, string>}
     */
    private function importAttributeSchema(string $directory, bool $dryRun, Context $context): array
    {
        $relations = [];
        $propertyGroups = [];
        $propertyAttributes = [];
        $propertyGroupIds = [];
        $relationCount = 0;

        foreach ($this->csvReader->rows($directory.'/okb-attributes.csv', [
            'category_group_id',
            'attribute_id',
            'attribute_name',
            'attribute_type',
            'multi_value',
            'feature_relevance',
        ]) as $row) {
            $this->requireValues($row, ['category_group_id', 'attribute_id', 'attribute_name', 'attribute_type', 'multi_value'], 'attribute');
            $multiValue = match ($row['multi_value']) {
                'true' => true,
                'false' => false,
                default => throw new \InvalidArgumentException(sprintf('OKB attribute %s has invalid multi_value "%s".', $row['attribute_id'], $row['multi_value'])),
            };
            $storage = OkbAttributeStorage::fromFeatureRelevance($row['feature_relevance']);
            $propertyGroupId = null;
            $customFieldName = null;
            if (OkbAttributeStorage::Property === $storage) {
                $propertyGroupId = OkbCatalogIdentity::propertyGroupId($row['attribute_name'], $row['attribute_type'], $multiValue);
                $propertyAttributes[$row['attribute_id']] = $propertyGroupId;
                $propertyGroupIds[$propertyGroupId] = true;
                $propertyGroups[$propertyGroupId] = [
                    'id' => $propertyGroupId,
                    'name' => $row['attribute_name'],
                    'displayType' => PropertyGroupDefinition::DISPLAY_TYPE_TEXT,
                    'sortingType' => PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC,
                    'filterable' => true,
                    'visibleOnProductDetailPage' => true,
                ];
            } else {
                $customFieldName = OkbCatalogIdentity::customFieldName($row['attribute_id']);
            }
            $relations[] = [
                'id' => OkbCatalogIdentity::categoryGroupAttributeId($row['category_group_id'], $row['attribute_id']),
                'categoryGroupId' => $row['category_group_id'],
                'attributeId' => $row['attribute_id'],
                'attributeName' => $row['attribute_name'],
                'attributeType' => $row['attribute_type'],
                'featureRelevance' => '' === $row['feature_relevance'] ? null : $row['feature_relevance'],
                'multiValue' => $multiValue,
                'storage' => $storage->value,
                'propertyGroupId' => $propertyGroupId,
                'customFieldName' => $customFieldName,
            ];
            ++$relationCount;

            if (self::BATCH_SIZE <= count($relations)) {
                if (!$dryRun) {
                    $this->propertyGroupRepository->upsert(array_values($propertyGroups), $context);
                    $this->categoryGroupAttributeRepository->upsert($relations, $context);
                }
                $relations = [];
                $propertyGroups = [];
            }
        }
        if (!$dryRun) {
            $this->propertyGroupRepository->upsert(array_values($propertyGroups), $context);
            $this->categoryGroupAttributeRepository->upsert($relations, $context);
        }

        return [$relationCount, count($propertyGroupIds), $propertyAttributes];
    }

    /** @param array<string, string> $propertyAttributes */
    private function importAllowedValues(string $directory, array $propertyAttributes, bool $dryRun, Context $context): int
    {
        $records = [];
        $optionIds = [];
        foreach ($this->csvReader->rows($directory.'/okb-attribute-allowed-values.csv', ['attribute_id', 'allowed_value_position', 'allowed_value']) as $row) {
            $propertyGroupId = $propertyAttributes[$row['attribute_id']] ?? null;
            if (null === $propertyGroupId) {
                continue;
            }
            $this->requireValues($row, ['allowed_value_position', 'allowed_value'], 'allowed attribute value');
            if (!ctype_digit($row['allowed_value_position'])) {
                throw new \InvalidArgumentException(sprintf('OKB attribute %s has invalid allowed_value_position "%s".', $row['attribute_id'], $row['allowed_value_position']));
            }
            $id = OkbCatalogIdentity::propertyOptionId($propertyGroupId, $row['allowed_value']);
            $optionIds[$id] = true;
            $records[$id] = [
                'id' => $id,
                'groupId' => $propertyGroupId,
                'name' => $row['allowed_value'],
                'position' => (int) $row['allowed_value_position'],
            ];
        }
        if ([] !== $records && !$dryRun) {
            $this->propertyOptionRepository->upsert(array_values($records), $context);
        }

        return count($optionIds);
    }

    /**
     * @param array<string, string> $row
     * @param list<string>          $keys
     */
    private function requireValues(array $row, array $keys, string $recordType): void
    {
        foreach ($keys as $key) {
            if ('' === $row[$key]) {
                throw new \InvalidArgumentException(sprintf('OKB %s record has an empty %s.', $recordType, $key));
            }
        }
    }

    /**
     * @template TEntityCollection of \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection
     *
     * @param EntityRepository<TEntityCollection> $repository
     * @param list<array<string, mixed>>          $records
     */
    private function upsertBatch(EntityRepository $repository, array &$records, bool $dryRun, Context $context): void
    {
        if (self::BATCH_SIZE > count($records)) {
            return;
        }
        if (!$dryRun) {
            $repository->upsert($records, $context);
        }
        $records = [];
    }

    /**
     * @template TEntityCollection of \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection
     *
     * @param EntityRepository<TEntityCollection> $repository
     * @param list<array<string, mixed>>          $records
     */
    private function upsertRemaining(EntityRepository $repository, array $records, bool $dryRun, Context $context): void
    {
        if ([] !== $records && !$dryRun) {
            $repository->upsert($records, $context);
        }
    }
}
