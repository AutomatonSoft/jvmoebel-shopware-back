<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog;

use Jv\Import\Core\Content\CatalogCategoryAttribute\CatalogCategoryAttributeCollection;
use Jv\Import\Core\Content\CatalogCategoryAttribute\CatalogCategoryAttributeEntity;
use Jv\Import\Service\Catalog\Dto\CatalogAttributeMapping;
use Jv\Import\Service\Catalog\Dto\CatalogSchemaImportResult;
use Jv\Import\Service\Catalog\Dto\CatalogSchemaSnapshot;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

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
        private CatalogAttributeMappingSynchronizer $attributeMappingSynchronizer,
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
        $mappings = $this->attributeMappingSynchronizer->synchronize(
            $snapshot->sourceCode,
            $snapshot->attributes,
            $this->existingAttributeMappings($snapshot->sourceCode, $context),
        );
        foreach ($mappings as $mapping) {
            $propertyGroupId = $mapping->propertyGroupId;
            if ($mapping->active && $mapping->enabled && 'property' === $mapping->storage && null !== $propertyGroupId) {
                $propertyAttributeGroups[$mapping->attributeId] = $propertyGroupId;
                $propertyGroupIds[$propertyGroupId] = true;
                if ($propertyGroupId === CatalogIdentity::propertyGroupId($mapping->attributeName, $mapping->attributeType, $mapping->multiValue)) {
                    $propertyGroups[$propertyGroupId] = [
                        'id' => $propertyGroupId,
                        'name' => $mapping->attributeName,
                        'displayType' => PropertyGroupDefinition::DISPLAY_TYPE_TEXT,
                        'sortingType' => PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC,
                        'filterable' => true,
                        'visibleOnProductDetailPage' => true,
                    ];
                }
            }
            $relations[] = $this->attributeRelation($mapping);
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
    private function attributeRelation(CatalogAttributeMapping $mapping): array
    {
        return [
            'id' => CatalogIdentity::categoryAttributeId($mapping->sourceCode, $mapping->categoryGroupId, $mapping->attributeId),
            'sourceCode' => $mapping->sourceCode,
            'categoryGroupId' => $mapping->categoryGroupId,
            'attributeId' => $mapping->attributeId,
            'attributeName' => $mapping->attributeName,
            'attributeType' => $mapping->attributeType,
            'featureRelevance' => $mapping->featureRelevance,
            'multiValue' => $mapping->multiValue,
            'active' => $mapping->active,
            'enabled' => $mapping->enabled,
            'storage' => $mapping->storage,
            'propertyGroupId' => $mapping->propertyGroupId,
            'customFieldName' => $mapping->customFieldName,
        ];
    }

    /** @return list<CatalogAttributeMapping> */
    private function existingAttributeMappings(string $sourceCode, Context $context): array
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('sourceCode', $sourceCode));
        /** @var CatalogCategoryAttributeCollection $entities */
        $entities = $this->categoryAttributeRepository->search($criteria, $context)->getEntities();

        return array_map(static fn (CatalogCategoryAttributeEntity $entity): CatalogAttributeMapping => new CatalogAttributeMapping(
            $entity->getSourceCode(),
            $entity->getCategoryGroupId(),
            $entity->getAttributeId(),
            $entity->getAttributeName(),
            $entity->getAttributeType(),
            $entity->getFeatureRelevance(),
            $entity->isMultiValue(),
            $entity->isActive(),
            $entity->isEnabled(),
            $entity->getStorage(),
            $entity->getPropertyGroupId(),
            $entity->getCustomFieldName(),
        ), array_values($entities->getElements()));
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
