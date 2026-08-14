<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\Catalog;

use Jv\Import\Service\Catalog\CatalogAttributeMappingSynchronizer;
use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\Catalog\Dto\CatalogAttribute;
use Jv\Import\Service\Catalog\Dto\CatalogAttributeMapping;
use PHPUnit\Framework\TestCase;

final class CatalogAttributeMappingSynchronizerTest extends TestCase
{
    public function testItCreatesAnEnabledMappingForANewObservedAttribute(): void
    {
        $mappings = (new CatalogAttributeMappingSynchronizer())->synchronize('source-a', [
            new CatalogAttribute('color', 'group-1', 'Color', 'STRING', 'FILTER', false, 'property'),
        ], []);

        self::assertEquals([
            new CatalogAttributeMapping(
                'source-a',
                'group-1',
                'color',
                'Color',
                'STRING',
                'FILTER',
                false,
                true,
                true,
                'property',
                CatalogIdentity::propertyGroupId('Color', 'STRING', false),
                null,
            ),
        ], $mappings);
    }

    public function testItRefreshesTheSourceSchemaWithoutOverwritingTheAdminMapping(): void
    {
        $mappings = (new CatalogAttributeMappingSynchronizer())->synchronize('source-a', [
            new CatalogAttribute('width', 'group-1', 'Width', 'FLOAT', 'SEARCH', false, 'property'),
        ], [
            new CatalogAttributeMapping('source-a', 'group-1', 'width', 'Old width', 'STRING', null, false, true, false, 'custom_field', null, 'jv_catalog_attributes'),
        ]);

        self::assertEquals([
            new CatalogAttributeMapping('source-a', 'group-1', 'width', 'Width', 'FLOAT', 'SEARCH', false, true, false, 'custom_field', null, 'jv_catalog_attributes'),
        ], $mappings);
    }

    public function testItMarksAnAttributeMissingFromTheCurrentSourceSnapshotInactiveAndKeepsItsConfiguration(): void
    {
        $mappings = (new CatalogAttributeMappingSynchronizer())->synchronize('source-a', [], [
            new CatalogAttributeMapping('source-a', 'group-1', 'width', 'Width', 'FLOAT', 'SEARCH', false, true, false, 'custom_field', null, 'jv_catalog_attributes'),
        ]);

        self::assertEquals([
            new CatalogAttributeMapping('source-a', 'group-1', 'width', 'Width', 'FLOAT', 'SEARCH', false, false, false, 'custom_field', null, 'jv_catalog_attributes'),
        ], $mappings);
    }

    public function testItRejectsMappingsForAnotherSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong to source "source-a"');

        (new CatalogAttributeMappingSynchronizer())->synchronize('source-a', [], [
            new CatalogAttributeMapping('source-b', 'group-1', 'width', 'Width', 'FLOAT', 'SEARCH', false, true, true, 'custom_field', null, 'jv_catalog_attributes'),
        ]);
    }
}
