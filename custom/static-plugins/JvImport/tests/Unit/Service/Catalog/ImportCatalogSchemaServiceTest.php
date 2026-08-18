<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\Catalog;

use Jv\Import\Core\Content\CatalogCategoryAttribute\CatalogCategoryAttributeCollection;
use Jv\Import\Service\Catalog\CatalogAttributeMappingSynchronizer;
use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\Catalog\Dto\CatalogAttribute;
use Jv\Import\Service\Catalog\Dto\CatalogCategoryGroup;
use Jv\Import\Service\Catalog\Dto\CatalogSchemaSnapshot;
use Jv\Import\Service\Catalog\ImportCatalogSchemaService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

final class ImportCatalogSchemaServiceTest extends TestCase
{
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
            new CatalogAttribute('colour', 'sofas', 'Colour', 'STRING', 'FILTER', false, 'property'),
        ], []), false, $context);

        self::assertSame(CatalogIdentity::categoryGroupId('source-a', 'sofas'), $relations[0]['categoryId']);
        self::assertSame(Defaults::LIVE_VERSION, $relations[0]['categoryVersionId']);
    }
}
