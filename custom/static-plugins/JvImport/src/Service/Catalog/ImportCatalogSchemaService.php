<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog;

use Jv\Import\Core\Content\CatalogCategoryAttribute\CatalogCategoryAttributeCollection;
use Jv\Import\Service\Catalog\Dto\CatalogAttribute;
use Jv\Import\Service\Catalog\Dto\CatalogSchemaImportResult;
use Jv\Import\Service\Catalog\Dto\CatalogSchemaSnapshot;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

final readonly class ImportCatalogSchemaService
{
    private const int BATCH_SIZE = 500;

    /**
     * @param EntityRepository<CategoryCollection>                 $categoryRepository
     * @param EntityRepository<PropertyGroupCollection>            $propertyGroupRepository
     * @param EntityRepository<PropertyGroupOptionCollection>      $propertyOptionRepository
     * @param EntityRepository<CatalogCategoryAttributeCollection> $categoryAttributeRepository
     */
    public function __construct(
        private EntityRepository $categoryRepository,
        private EntityRepository $propertyGroupRepository,
        private EntityRepository $propertyOptionRepository,
        private EntityRepository $categoryAttributeRepository,
    ) {
    }

    public function execute(CatalogSchemaSnapshot $snapshot, bool $dryRun, Context $context): CatalogSchemaImportResult
    {
        $categoryGroups = $this->importCategoryGroups($snapshot, $dryRun, $context);
        $categories = $this->importCategories($snapshot, $dryRun, $context);
        [$attributeRelations, $propertyGroups, $propertyAttributes] = $this->importAttributeSchema($snapshot, $dryRun, $context);
        $propertyOptions = $this->importAllowedValues($snapshot, $propertyAttributes, $dryRun, $context);

        return new CatalogSchemaImportResult($categoryGroups, $categories, $attributeRelations, $propertyGroups, $propertyOptions);
    }

    private function importCategoryGroups(CatalogSchemaSnapshot $snapshot, bool $dryRun, Context $context): int
    {
        $records = [];
        foreach ($snapshot->categoryGroups as $group) {
            $records[] = [
                'id' => CatalogIdentity::categoryGroupId($snapshot->sourceCode, $group->sourceKey),
                'name' => $group->name,
                'active' => true,
                'visible' => true,
                'type' => CategoryDefinition::TYPE_PAGE,
            ];
            $this->upsertBatch($this->categoryRepository, $records, $dryRun, $context);
        }
        $this->upsertRemaining($this->categoryRepository, $records, $dryRun, $context);

        return count($snapshot->categoryGroups);
    }

    private function importCategories(CatalogSchemaSnapshot $snapshot, bool $dryRun, Context $context): int
    {
        $records = [];
        foreach ($snapshot->categories as $category) {
            $records[] = [
                'id' => CatalogIdentity::categoryId($snapshot->sourceCode, $category->sourceKey),
                'parentId' => CatalogIdentity::categoryGroupId($snapshot->sourceCode, $category->categoryGroupSourceKey),
                'name' => $category->name,
                'active' => true,
                'visible' => true,
                'type' => CategoryDefinition::TYPE_PAGE,
            ];
            $this->upsertBatch($this->categoryRepository, $records, $dryRun, $context);
        }
        $this->upsertRemaining($this->categoryRepository, $records, $dryRun, $context);

        return count($snapshot->categories);
    }

    /** @return array{0: int, 1: int, 2: array<string, string>} */
    private function importAttributeSchema(CatalogSchemaSnapshot $snapshot, bool $dryRun, Context $context): array
    {
        $relations = [];
        $propertyGroups = [];
        $propertyAttributeGroups = [];
        $propertyGroupIds = [];
        foreach ($snapshot->attributes as $attribute) {
            $propertyGroupId = null;
            if ('property' === $attribute->storage) {
                $propertyGroupId = CatalogIdentity::propertyGroupId($attribute->name, $attribute->type, $attribute->multiValue);
                $propertyAttributeGroups[$attribute->sourceKey] = $propertyGroupId;
                $propertyGroupIds[$propertyGroupId] = true;
                $propertyGroups[$propertyGroupId] = [
                    'id' => $propertyGroupId,
                    'name' => $attribute->name,
                    'displayType' => PropertyGroupDefinition::DISPLAY_TYPE_TEXT,
                    'sortingType' => PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC,
                    'filterable' => true,
                    'visibleOnProductDetailPage' => true,
                ];
            }
            $relations[] = $this->attributeRelation($snapshot, $attribute, $propertyGroupId);
            if (self::BATCH_SIZE <= count($relations)) {
                if (!$dryRun) {
                    $this->propertyGroupRepository->upsert(array_values($propertyGroups), $context);
                    $this->categoryAttributeRepository->upsert($relations, $context);
                }
                $relations = [];
                $propertyGroups = [];
            }
        }
        if (!$dryRun) {
            $this->propertyGroupRepository->upsert(array_values($propertyGroups), $context);
            $this->categoryAttributeRepository->upsert($relations, $context);
        }

        return [count($snapshot->attributes), count($propertyGroupIds), $propertyAttributeGroups];
    }

    /** @return array<string, mixed> */
    private function attributeRelation(CatalogSchemaSnapshot $snapshot, CatalogAttribute $attribute, ?string $propertyGroupId): array
    {
        return [
            'id' => CatalogIdentity::categoryAttributeId($snapshot->sourceCode, $attribute->categoryGroupSourceKey, $attribute->sourceKey),
            'sourceCode' => $snapshot->sourceCode,
            'categoryGroupId' => $attribute->categoryGroupSourceKey,
            'attributeId' => $attribute->sourceKey,
            'attributeName' => $attribute->name,
            'attributeType' => $attribute->type,
            'featureRelevance' => $attribute->sourceRelevance,
            'multiValue' => $attribute->multiValue,
            'storage' => $attribute->storage,
            'propertyGroupId' => $propertyGroupId,
            'customFieldName' => null,
        ];
    }

    /** @param array<string, string> $propertyAttributeGroups */
    private function importAllowedValues(CatalogSchemaSnapshot $snapshot, array $propertyAttributeGroups, bool $dryRun, Context $context): int
    {
        $records = [];
        foreach ($snapshot->allowedValues as $allowedValue) {
            $propertyGroupId = $propertyAttributeGroups[$allowedValue->attributeSourceKey] ?? null;
            if (null === $propertyGroupId) {
                continue;
            }
            $id = CatalogIdentity::propertyOptionId($propertyGroupId, $allowedValue->value);
            $records[$id] = ['id' => $id, 'groupId' => $propertyGroupId, 'name' => $allowedValue->value, 'position' => $allowedValue->position];
        }
        if ([] !== $records && !$dryRun) {
            $this->propertyOptionRepository->upsert(array_values($records), $context);
        }

        return count($records);
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
