<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog;

use Doctrine\DBAL\Connection;
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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
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
        private ?Connection $connection = null,
        private ?EntityIndexerRegistry $indexerRegistry = null,
    ) {
    }

    public function execute(CatalogSchemaSnapshot $snapshot, bool $dryRun, Context $context): CatalogSchemaImportResult
    {
        $this->validateStructure($snapshot);
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $propertyTranslationLanguageIds = $this->propertyTranslationLanguageIds();
        $this->importNavigationCategories($snapshot, $dryRun, $context);
        $categoryGroups = $this->importCategoryGroups($snapshot, $dryRun, $context);
        $categories = $this->importCategories($snapshot, $dryRun, $context);
        [$attributeRelations, $propertyGroups, $propertyAttributes] = $this->importAttributeSchema($snapshot, $propertyTranslationLanguageIds, $dryRun, $context);
        $propertyOptions = $this->importAllowedValues($snapshot, $propertyAttributes, $propertyTranslationLanguageIds, $dryRun, $context);
        if (!$dryRun) {
            $this->indexerRegistry?->index(false, [], ['category.indexer']);
        }

        return new CatalogSchemaImportResult($categoryGroups, $categories, $attributeRelations, $propertyGroups, $propertyOptions);
    }

    private function importCategoryGroups(CatalogSchemaSnapshot $snapshot, bool $dryRun, Context $context): int
    {
        $parentNavigationKeys = [];
        foreach ($snapshot->categoryGroupNavigationMappings as $mapping) {
            $parentNavigationKeys[$mapping->categoryGroupSourceKey] = $mapping->navigationSourceKey;
        }
        $records = [];
        foreach ($snapshot->categoryGroups as $group) {
            $records[] = [
                'id' => CatalogIdentity::categoryGroupId($snapshot->sourceCode, $group->sourceKey),
                ...isset($parentNavigationKeys[$group->sourceKey]) ? ['parentId' => CatalogIdentity::navigationCategoryId($snapshot->sourceCode, $parentNavigationKeys[$group->sourceKey])] : [],
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

    private function importNavigationCategories(CatalogSchemaSnapshot $snapshot, bool $dryRun, Context $context): void
    {
        $records = [];
        foreach ($snapshot->navigationCategories as $navigationCategory) {
            $records[] = [
                'id' => CatalogIdentity::navigationCategoryId($snapshot->sourceCode, $navigationCategory->sourceKey),
                'parentId' => null === $navigationCategory->parentSourceKey
                    ? CatalogIdentity::navigationRootId()
                    : CatalogIdentity::navigationCategoryId($snapshot->sourceCode, $navigationCategory->parentSourceKey),
                'name' => $navigationCategory->name,
                'active' => true,
                'visible' => true,
                'type' => CategoryDefinition::TYPE_PAGE,
            ];
            $this->upsertBatch($this->categoryRepository, $records, $dryRun, $context);
        }
        $this->upsertRemaining($this->categoryRepository, $records, $dryRun, $context);
    }

    private function validateStructure(CatalogSchemaSnapshot $snapshot): void
    {
        if ([] === $snapshot->navigationCategories && [] === $snapshot->categoryGroupNavigationMappings) {
            return;
        }
        $navigationParents = [];
        foreach ($snapshot->navigationCategories as $navigationCategory) {
            if (isset($navigationParents[$navigationCategory->sourceKey])) {
                throw new \InvalidArgumentException(sprintf('Catalog navigation category %s is duplicated.', $navigationCategory->sourceKey));
            }
            $navigationParents[$navigationCategory->sourceKey] = $navigationCategory->parentSourceKey;
        }
        foreach ($navigationParents as $key => $parentKey) {
            if (null === $parentKey) {
                continue;
            }
            if (!array_key_exists($parentKey, $navigationParents)) {
                throw new \InvalidArgumentException(sprintf('Catalog navigation category %s references unknown parent %s.', $key, $parentKey));
            }
            if (null !== $navigationParents[$parentKey]) {
                throw new \InvalidArgumentException(sprintf('Catalog navigation category %s is deeper than level 2.', $key));
            }
        }
        $groups = [];
        foreach ($snapshot->categoryGroups as $group) {
            $groups[$group->sourceKey] = true;
        }
        $mappedGroups = [];
        foreach ($snapshot->categoryGroupNavigationMappings as $mapping) {
            if (!isset($groups[$mapping->categoryGroupSourceKey])) {
                throw new \InvalidArgumentException(sprintf('Catalog navigation mapping references unknown category group %s.', $mapping->categoryGroupSourceKey));
            }
            if (isset($mappedGroups[$mapping->categoryGroupSourceKey])) {
                throw new \InvalidArgumentException(sprintf('Catalog category group %s has more than one navigation parent.', $mapping->categoryGroupSourceKey));
            }
            if (!isset($navigationParents[$mapping->navigationSourceKey])) {
                throw new \InvalidArgumentException(sprintf('Catalog category group %s references navigation parent %s that is not on level 2.', $mapping->categoryGroupSourceKey, $mapping->navigationSourceKey));
            }
            $mappedGroups[$mapping->categoryGroupSourceKey] = true;
        }
        foreach ($groups as $groupKey => $_) {
            if (!isset($mappedGroups[$groupKey])) {
                throw new \InvalidArgumentException(sprintf('Catalog category group %s has no navigation parent.', $groupKey));
            }
        }
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

    /**
     * @param list<string> $propertyTranslationLanguageIds
     *
     * @return array{0: int, 1: int, 2: array<string, string>}
     */
    private function importAttributeSchema(CatalogSchemaSnapshot $snapshot, array $propertyTranslationLanguageIds, bool $dryRun, Context $context): array
    {
        $relations = [];
        $propertyGroups = [];
        $propertyAttributeGroups = [];
        $propertyGroupIds = [];
        $observedMappings = $this->attributeMappingSynchronizer->synchronize(
            $snapshot->sourceCode,
            $snapshot->attributes,
            [],
        );
        $observedKeys = [];
        foreach ($observedMappings as $mapping) {
            $observedKeys[$this->attributeMappingKey($mapping->categoryGroupId, $mapping->attributeId)] = true;
        }
        [$existingIds, $inactiveMappings] = $this->existingAttributeMappings($snapshot->sourceCode, $observedKeys, $context);
        $mappings = [...$observedMappings, ...$inactiveMappings];
        foreach ($mappings as $mapping) {
            $propertyGroupId = $mapping->propertyGroupId;
            if ($mapping->active && $mapping->enabled && null !== $propertyGroupId) {
                $propertyAttributeGroups[$mapping->attributeId] = $propertyGroupId;
                $propertyGroupIds[$propertyGroupId] = true;
                if ($propertyGroupId === CatalogIdentity::propertyGroupId($mapping->attributeName)) {
                    $propertyGroups[$propertyGroupId] ??= [
                        'id' => $propertyGroupId,
                        'name' => $mapping->attributeName,
                        'displayType' => PropertyGroupDefinition::DISPLAY_TYPE_TEXT,
                        'sortingType' => PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC,
                        'filterable' => false,
                        'visibleOnProductDetailPage' => true,
                        'translations' => $this->translations($mapping->attributeName, $propertyTranslationLanguageIds),
                    ];
                    $propertyGroups[$propertyGroupId]['filterable'] = $propertyGroups[$propertyGroupId]['filterable'] || $this->isFilterable($mapping->featureRelevance);
                }
            }
            $relations[] = $this->attributeRelation($mapping, $existingIds[$this->attributeMappingKey($mapping->categoryGroupId, $mapping->attributeId)] ?? null);
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
    private function attributeRelation(CatalogAttributeMapping $mapping, ?string $existingId): array
    {
        return [
            'id' => $existingId ?? CatalogIdentity::categoryAttributeId($mapping->sourceCode, $mapping->categoryGroupId, $mapping->attributeId),
            'sourceCode' => $mapping->sourceCode,
            'categoryGroupId' => $mapping->categoryGroupId,
            'categoryId' => CatalogIdentity::categoryGroupId($mapping->sourceCode, $mapping->categoryGroupId),
            'categoryVersionId' => Defaults::LIVE_VERSION,
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

    private function isFilterable(?string $featureRelevance): bool
    {
        foreach (['FILTER', 'NAVIGATION', 'SEARCH'] as $feature) {
            if (in_array($feature, explode('|', (string) $featureRelevance), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, true> $observedKeys
     *
     * @return array{0: array<string, string>, 1: list<CatalogAttributeMapping>}
     */
    private function existingAttributeMappings(string $sourceCode, array $observedKeys, Context $context): array
    {
        if (null !== $this->connection) {
            return $this->existingAttributeMappingsFromDatabase($sourceCode, $observedKeys);
        }

        $criteria = (new Criteria())->addFilter(new EqualsFilter('sourceCode', $sourceCode));
        /** @var CatalogCategoryAttributeCollection $entities */
        $entities = $this->categoryAttributeRepository->search($criteria, $context)->getEntities();

        $ids = [];
        $inactiveMappings = [];
        foreach ($entities as $entity) {
            $mapping = $this->mappingFromEntity($entity);
            $key = $this->attributeMappingKey($mapping->categoryGroupId, $mapping->attributeId);
            if (isset($observedKeys[$key])) {
                $ids[$key] = $entity->getId();

                continue;
            }
            $inactiveMappings[] = new CatalogAttributeMapping(
                $mapping->sourceCode,
                $mapping->categoryGroupId,
                $mapping->attributeId,
                $mapping->attributeName,
                $mapping->attributeType,
                $mapping->featureRelevance,
                $mapping->multiValue,
                false,
                $mapping->enabled,
                $mapping->storage,
                $mapping->propertyGroupId,
                $mapping->customFieldName,
            );
            $ids[$key] = $entity->getId();
        }

        return [$ids, $inactiveMappings];
    }

    /**
     * @param array<string, true> $observedKeys
     *
     * @return array{0: array<string, string>, 1: list<CatalogAttributeMapping>}
     */
    private function existingAttributeMappingsFromDatabase(string $sourceCode, array $observedKeys): array
    {
        $ids = [];
        $inactiveMappings = [];
        foreach ($this->connection->iterateAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(`id`)) AS `id`, `category_group_id`, `attribute_id`, `attribute_name`, `attribute_type`,
                       `feature_relevance`, `multi_value`, `enabled`, `storage`, LOWER(HEX(`property_group_id`)) AS `property_group_id`, `custom_field_name`
                FROM `jv_catalog_category_attribute`
                WHERE `source_code` = :sourceCode
                SQL,
            ['sourceCode' => $sourceCode],
        ) as $row) {
            $key = $this->attributeMappingKey($row['category_group_id'], $row['attribute_id']);
            $ids[$key] = $row['id'];
            if (isset($observedKeys[$key])) {
                continue;
            }
            $inactiveMappings[] = new CatalogAttributeMapping(
                $sourceCode,
                $row['category_group_id'],
                $row['attribute_id'],
                $row['attribute_name'],
                $row['attribute_type'],
                $row['feature_relevance'],
                (bool) $row['multi_value'],
                false,
                (bool) $row['enabled'],
                $row['storage'],
                $row['property_group_id'],
                $row['custom_field_name'],
            );
        }

        return [$ids, $inactiveMappings];
    }

    private function mappingFromEntity(CatalogCategoryAttributeEntity $mapping): CatalogAttributeMapping
    {
        return new CatalogAttributeMapping(
            $mapping->getSourceCode(),
            $mapping->getCategoryGroupId(),
            $mapping->getAttributeId(),
            $mapping->getAttributeName(),
            $mapping->getAttributeType(),
            $mapping->getFeatureRelevance(),
            $mapping->isMultiValue(),
            $mapping->isActive(),
            $mapping->isEnabled(),
            $mapping->getStorage(),
            $mapping->getPropertyGroupId(),
            $mapping->getCustomFieldName(),
        );
    }

    private function attributeMappingKey(string $categoryGroupId, string $attributeId): string
    {
        return $categoryGroupId."\0".$attributeId;
    }

    /**
     * @param array<string, string> $propertyAttributeGroups
     * @param list<string>          $propertyTranslationLanguageIds
     */
    private function importAllowedValues(CatalogSchemaSnapshot $snapshot, array $propertyAttributeGroups, array $propertyTranslationLanguageIds, bool $dryRun, Context $context): int
    {
        $records = [];
        foreach ($snapshot->allowedValues as $allowedValue) {
            $propertyGroupId = $propertyAttributeGroups[$allowedValue->attributeSourceKey] ?? null;
            if (null === $propertyGroupId) {
                continue;
            }
            $id = CatalogIdentity::propertyOptionId($propertyGroupId, $allowedValue->value);
            $records[$id] = [
                'id' => $id,
                'groupId' => $propertyGroupId,
                'name' => $allowedValue->value,
                'position' => $allowedValue->position,
                'translations' => $this->translations($allowedValue->value, $propertyTranslationLanguageIds),
            ];
        }
        if ([] !== $records && !$dryRun) {
            $this->propertyOptionRepository->upsert(array_values($records), $context);
        }

        return count($records);
    }

    /** @return list<string> */
    private function propertyTranslationLanguageIds(): array
    {
        if (null === $this->connection) {
            return [Defaults::LANGUAGE_SYSTEM];
        }

        /** @var list<string> $languageIds */
        $languageIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM `language`');

        return array_values(array_unique($languageIds));
    }

    /** @param list<string> $languageIds
     *
     * @return array<string, array{name: string}>
     */
    private function translations(string $name, array $languageIds): array
    {
        $translations = [];
        foreach ($languageIds as $languageId) {
            $translations[$languageId] = ['name' => $name];
        }

        return $translations;
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
