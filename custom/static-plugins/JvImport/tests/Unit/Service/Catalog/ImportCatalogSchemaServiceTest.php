<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\Catalog;

use Doctrine\DBAL\Connection;
use Jv\Import\Core\Content\CatalogCategoryAttribute\CatalogCategoryAttributeCollection;
use Jv\Import\Core\Content\CatalogCategoryAttribute\CatalogCategoryAttributeEntity;
use Jv\Import\Service\Catalog\CatalogAttributeMappingSynchronizer;
use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\Catalog\Dto\CatalogAllowedValue;
use Jv\Import\Service\Catalog\Dto\CatalogAttribute;
use Jv\Import\Service\Catalog\Dto\CatalogCategoryGroup;
use Jv\Import\Service\Catalog\Dto\CatalogCategoryGroupNavigationMapping;
use Jv\Import\Service\Catalog\Dto\CatalogNavigationCategory;
use Jv\Import\Service\Catalog\Dto\CatalogSchemaSnapshot;
use Jv\Import\Service\Catalog\ImportCatalogSchemaService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

final class ImportCatalogSchemaServiceTest extends TestCase
{
    public function testItStreamsExistingMappingsFromTheDatabaseInsteadOfHydratingEveryEntity(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $propertyOptionRepository = $this->createMock(EntityRepository::class);
        $mappingRepository = $this->createMock(EntityRepository::class);
        $connection = $this->createMock(Connection::class);
        $context = Context::createDefaultContext();
        $relations = [];
        $existingId = '9e75660a1ef94814bb09d47ce61182cf';

        $categoryRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $propertyGroupRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $propertyOptionRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $mappingRepository->expects(self::never())->method('search');
        $mappingRepository->expects(self::once())->method('upsert')->willReturnCallback(
            static function (array $records) use (&$relations, $context): EntityWrittenContainerEvent {
                $relations = $records;

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );
        $connection->expects(self::once())->method('iterateAssociative')->willReturnCallback(
            static function (string $sql, array $parameters) use ($existingId): \ArrayIterator {
                self::assertStringContainsString('FROM `jv_catalog_category_attribute`', $sql);
                self::assertSame(['sourceCode' => 'source-a'], $parameters);

                return new \ArrayIterator([[
                    'id' => $existingId,
                    'category_group_id' => 'sofas',
                    'attribute_id' => 'width',
                    'attribute_name' => 'Old width',
                    'attribute_type' => 'STRING',
                    'feature_relevance' => null,
                    'multi_value' => 0,
                    'enabled' => 1,
                    'storage' => 'property',
                    'property_group_id' => CatalogIdentity::legacyPropertyGroupId('Old width', 'STRING', false),
                    'custom_field_name' => null,
                ]]);
            },
        );

        (new ImportCatalogSchemaService(
            $categoryRepository,
            $propertyGroupRepository,
            $propertyOptionRepository,
            $mappingRepository,
            new CatalogAttributeMappingSynchronizer(),
            $connection,
        ))->execute(new CatalogSchemaSnapshot('source-a', [], [], [
            new CatalogAttribute('width', 'sofas', 'Width', 'FLOAT', 'FILTER', false),
        ], []), false, $context);

        self::assertSame($existingId, $relations[0]['id']);
        self::assertSame(CatalogIdentity::propertyGroupId('Width'), $relations[0]['propertyGroupId']);
    }

    public function testItUpdatesALegacySchemaRelationUsingItsExistingId(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $propertyOptionRepository = $this->createMock(EntityRepository::class);
        $mappingRepository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();
        $legacyId = '9e75660a1ef94814bb09d47ce61182cf';
        $legacyRelation = new CatalogCategoryAttributeEntity();
        $legacyRelation->setId($legacyId);
        $legacyRelation->setSourceCode('source-a');
        $legacyRelation->setCategoryGroupId('sofas');
        $legacyRelation->setCategoryId(CatalogIdentity::categoryGroupId('source-a', 'sofas'));
        $legacyRelation->setCategoryVersionId(Defaults::LIVE_VERSION);
        $legacyRelation->setAttributeId('colour');
        $legacyRelation->setAttributeName('Old colour');
        $legacyRelation->setAttributeType('STRING');
        $legacyRelation->setMultiValue(false);
        $legacyRelation->setActive(true);
        $legacyRelation->setEnabled(true);
        $legacyRelation->setStorage('custom_field');
        $relations = [];

        $categoryRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $propertyGroupRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $propertyOptionRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $mappingRepository->method('search')->willReturn(new EntitySearchResult(
            'jv_catalog_category_attribute',
            1,
            new CatalogCategoryAttributeCollection([$legacyRelation]),
            null,
            new Criteria(),
            $context,
        ));
        $mappingRepository->expects(self::once())->method('upsert')->willReturnCallback(
            static function (array $records) use (&$relations, $context): EntityWrittenContainerEvent {
                $relations = $records;

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );

        (new ImportCatalogSchemaService(
            $categoryRepository,
            $propertyGroupRepository,
            $propertyOptionRepository,
            $mappingRepository,
            new CatalogAttributeMappingSynchronizer(),
        ))->execute(new CatalogSchemaSnapshot('source-a', [], [], [
            new CatalogAttribute('colour', 'sofas', 'Colour', 'STRING', 'FILTER', false),
        ], []), false, $context);

        self::assertSame($legacyId, $relations[0]['id']);
        self::assertSame('property', $relations[0]['storage']);
        self::assertSame(CatalogIdentity::propertyGroupId('Colour'), $relations[0]['propertyGroupId']);
    }

    public function testItDisablesImmediateIndexingForBulkSchemaWrites(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $propertyOptionRepository = $this->createMock(EntityRepository::class);
        $mappingRepository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();

        $categoryRepository->expects(self::once())->method('upsert')->willReturnCallback(
            static function (array $records, Context $writeContext) use ($context): EntityWrittenContainerEvent {
                self::assertNotEmpty($records);
                self::assertTrue($writeContext->hasState(EntityIndexerRegistry::DISABLE_INDEXING));

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );
        $propertyGroupRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $propertyOptionRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $mappingRepository->method('search')->willReturn(new EntitySearchResult(
            'jv_catalog_category_attribute',
            0,
            new CatalogCategoryAttributeCollection(),
            null,
            new Criteria(),
            $context,
        ));
        $mappingRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));

        (new ImportCatalogSchemaService(
            $categoryRepository,
            $propertyGroupRepository,
            $propertyOptionRepository,
            $mappingRepository,
            new CatalogAttributeMappingSynchronizer(),
        ))->execute(new CatalogSchemaSnapshot('source-a', [
            new CatalogCategoryGroup('sofas', 'Sofas'),
        ], [], [], []), false, $context);
    }

    public function testItImportsNavigationBeforeAssigningItsCategoryGroup(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $propertyOptionRepository = $this->createMock(EntityRepository::class);
        $mappingRepository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();
        $writes = [];

        $categoryRepository->expects(self::exactly(2))->method('upsert')->willReturnCallback(
            static function (array $records) use (&$writes, $context): EntityWrittenContainerEvent {
                $writes[] = $records;

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );
        $propertyGroupRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $propertyOptionRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $mappingRepository->method('search')->willReturn(new EntitySearchResult('jv_catalog_category_attribute', 0, new CatalogCategoryAttributeCollection(), null, new Criteria(), $context));
        $mappingRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));

        (new ImportCatalogSchemaService($categoryRepository, $propertyGroupRepository, $propertyOptionRepository, $mappingRepository, new CatalogAttributeMappingSynchronizer()))->execute(
            new CatalogSchemaSnapshot('source-a', [new CatalogCategoryGroup('sofas', 'Sofas')], [], [], [], [
                new CatalogNavigationCategory('l1:furniture', null, 'Furniture'),
                new CatalogNavigationCategory('l2:living-room', 'l1:furniture', 'Living room'),
            ], [new CatalogCategoryGroupNavigationMapping('sofas', 'l2:living-room')]),
            false,
            $context,
        );

        self::assertSame(CatalogIdentity::navigationRootId(), $writes[0][0]['parentId']);
        self::assertSame(CatalogIdentity::navigationCategoryId('source-a', 'l1:furniture'), $writes[0][1]['parentId']);
        self::assertSame(CatalogIdentity::navigationCategoryId('source-a', 'l2:living-room'), $writes[1][0]['parentId']);
    }

    public function testItRejectsCategoryGroupMappingToLevelOneNavigation(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $propertyOptionRepository = $this->createMock(EntityRepository::class);
        $mappingRepository = $this->createMock(EntityRepository::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not on level 2');

        (new ImportCatalogSchemaService($categoryRepository, $propertyGroupRepository, $propertyOptionRepository, $mappingRepository, new CatalogAttributeMappingSynchronizer()))->execute(
            new CatalogSchemaSnapshot('source-a', [new CatalogCategoryGroup('sofas', 'Sofas')], [], [], [], [
                new CatalogNavigationCategory('l1:furniture', null, 'Furniture'),
            ], [new CatalogCategoryGroupNavigationMapping('sofas', 'l1:furniture')]),
            true,
            Context::createDefaultContext(),
        );
    }

    public function testItCreatesAVisibleNonFilterablePropertyForAnOkbProductDetailsAttribute(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $propertyOptionRepository = $this->createMock(EntityRepository::class);
        $mappingRepository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();
        $propertyGroups = [];

        $categoryRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $propertyGroupRepository->expects(self::once())->method('upsert')->willReturnCallback(
            static function (array $records) use (&$propertyGroups, $context): EntityWrittenContainerEvent {
                $propertyGroups = $records;

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );
        $propertyOptionRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $mappingRepository->method('search')->willReturn(new EntitySearchResult(
            'jv_catalog_category_attribute',
            0,
            new CatalogCategoryAttributeCollection(),
            null,
            new Criteria(),
            $context,
        ));
        $mappingRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));

        (new ImportCatalogSchemaService(
            $categoryRepository,
            $propertyGroupRepository,
            $propertyOptionRepository,
            $mappingRepository,
            new CatalogAttributeMappingSynchronizer(),
        ))->execute(new CatalogSchemaSnapshot('source-a', [], [], [
            new CatalogAttribute('width', 'sofas', 'Width', 'FLOAT', 'PRODUCT_DETAILS', false),
        ], []), false, $context);

        self::assertSame([[
            'id' => CatalogIdentity::propertyGroupId('Width'),
            'name' => 'Width',
            'displayType' => 'text',
            'sortingType' => 'alphanumeric',
            'filterable' => false,
            'visibleOnProductDetailPage' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Width'],
            ],
        ]], $propertyGroups);
    }

    public function testItCreatesOneFilterablePropertyForEquivalentAttributes(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $propertyOptionRepository = $this->createMock(EntityRepository::class);
        $mappingRepository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();
        $propertyGroups = [];

        $categoryRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $propertyGroupRepository->expects(self::once())->method('upsert')->willReturnCallback(
            static function (array $records) use (&$propertyGroups, $context): EntityWrittenContainerEvent {
                $propertyGroups = $records;

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );
        $propertyOptionRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $mappingRepository->method('search')->willReturn(new EntitySearchResult(
            'jv_catalog_category_attribute',
            0,
            new CatalogCategoryAttributeCollection(),
            null,
            new Criteria(),
            $context,
        ));
        $mappingRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));

        (new ImportCatalogSchemaService(
            $categoryRepository,
            $propertyGroupRepository,
            $propertyOptionRepository,
            $mappingRepository,
            new CatalogAttributeMappingSynchronizer(),
        ))->execute(new CatalogSchemaSnapshot('source-a', [], [], [
            new CatalogAttribute('width-as-number', 'sofas', 'Width', 'FLOAT', 'PRODUCT_DETAILS', false),
            new CatalogAttribute('width-as-text', 'tables', ' width ', 'STRING', 'FILTER', true),
        ], []), false, $context);

        self::assertSame([[
            'id' => CatalogIdentity::propertyGroupId('Width'),
            'name' => 'Width',
            'displayType' => 'text',
            'sortingType' => 'alphanumeric',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Width'],
            ],
        ]], $propertyGroups);
    }

    public function testItStoresTheShopwareCategoryGroupIdWithEachAttributeMapping(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $propertyOptionRepository = $this->createMock(EntityRepository::class);
        $mappingRepository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();
        $relations = [];

        $categoryRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $propertyGroupRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $propertyOptionRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $mappingRepository->method('search')->willReturn(new EntitySearchResult(
            'jv_catalog_category_attribute',
            0,
            new CatalogCategoryAttributeCollection(),
            null,
            new Criteria(),
            $context,
        ));
        $mappingRepository->expects(self::once())->method('upsert')->willReturnCallback(
            static function (array $records) use (&$relations, $context): EntityWrittenContainerEvent {
                $relations = $records;

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );

        (new ImportCatalogSchemaService(
            $categoryRepository,
            $propertyGroupRepository,
            $propertyOptionRepository,
            $mappingRepository,
            new CatalogAttributeMappingSynchronizer(),
        ))->execute(new CatalogSchemaSnapshot('source-a', [
            new CatalogCategoryGroup('sofas', 'Sofas'),
        ], [], [
            new CatalogAttribute('colour', 'sofas', 'Colour', 'STRING', 'FILTER', false),
        ], []), false, $context);

        self::assertSame(CatalogIdentity::categoryGroupId('source-a', 'sofas'), $relations[0]['categoryId']);
        self::assertSame(Defaults::LIVE_VERSION, $relations[0]['categoryVersionId']);
    }

    public function testItCreatesDirectTranslationsForEveryShopwareLanguage(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $propertyOptionRepository = $this->createMock(EntityRepository::class);
        $mappingRepository = $this->createMock(EntityRepository::class);
        $connection = $this->createMock(Connection::class);
        $context = Context::createDefaultContext();
        $propertyGroups = [];
        $propertyOptions = [];
        $germanLanguageId = Defaults::LANGUAGE_SYSTEM;
        $englishLanguageId = '23f3b8dcf6244c26934f3b6b1426f657';

        $connection->method('iterateAssociative')->willReturn(new \ArrayIterator());
        $connection->expects(self::once())->method('fetchFirstColumn')->with('SELECT LOWER(HEX(`id`)) FROM `language`')->willReturn([$germanLanguageId, $englishLanguageId]);
        $categoryRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));
        $propertyGroupRepository->expects(self::once())->method('upsert')->willReturnCallback(
            static function (array $records) use (&$propertyGroups, $context): EntityWrittenContainerEvent {
                $propertyGroups = $records;

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );
        $propertyOptionRepository->expects(self::once())->method('upsert')->willReturnCallback(
            static function (array $records) use (&$propertyOptions, $context): EntityWrittenContainerEvent {
                $propertyOptions = $records;

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );
        $mappingRepository->method('upsert')->willReturn(EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []));

        (new ImportCatalogSchemaService(
            $categoryRepository,
            $propertyGroupRepository,
            $propertyOptionRepository,
            $mappingRepository,
            new CatalogAttributeMappingSynchronizer(),
            $connection,
        ))->execute(new CatalogSchemaSnapshot('source-a', [], [], [
            new CatalogAttribute('colour', 'sofas', 'Colour', 'STRING', 'FILTER', false),
        ], [
            new CatalogAllowedValue('colour', 1, 'Blue'),
        ]), false, $context);

        self::assertSame([
            $germanLanguageId => ['name' => 'Colour'],
            $englishLanguageId => ['name' => 'Colour'],
        ], $propertyGroups[0]['translations']);
        self::assertSame([
            $germanLanguageId => ['name' => 'Blue'],
            $englishLanguageId => ['name' => 'Blue'],
        ], $propertyOptions[0]['translations']);
    }
}
