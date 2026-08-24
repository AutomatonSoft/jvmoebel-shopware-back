<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Core\Content\CatalogCategoryAttribute\CatalogCategoryAttributeCollection;
use Jv\Import\Integration\Okb\Profile\CatalogProductImportProfile;
use Jv\Import\Service\Catalog\CatalogIdentity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;

final class CatalogProductImportTest extends AbstractCosmoShopImportExportTestCase
{
    public function testItRejectsBothRowsWhenAnOptionValueExceedsTheShopwareLimit(): void
    {
        $context = Context::createDefaultContext();
        $suffix = bin2hex(random_bytes(5));
        $productNumber = 'CATALOG-INVALID-'.$suffix;
        $parentId = Uuid::randomHex();
        $categoryGroupId = 'group-'.$suffix;
        $categoryId = 'category-'.$suffix;
        $propertyGroupId = CatalogIdentity::propertyGroupId('Color '.$suffix);
        $manualGroupId = Uuid::randomHex();
        $manualOptionId = Uuid::randomHex();
        $this->createFixture($parentId, $productNumber, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $manualOptionId, $context);
        $validParentId = Uuid::randomHex();
        $validProductNumber = 'CATALOG-VALID-'.$suffix;
        $this->createParent($validParentId, $validProductNumber, $context);
        $profileId = CatalogProductImportProfile::definition()['id'];
        $this->profileRepository()->upsert([CatalogProductImportProfile::definition()], $context);

        try {
            $invalidCsv = $this->catalogCsv($productNumber, '4260174423463', $categoryId, $categoryGroupId, 1200, str_repeat('x', 256));
            $validCsv = $this->catalogCsv($validProductNumber, '4260174423464', $categoryId, $categoryGroupId, 1200, 'Brown');
            $progress = $this->import($profileId, $this->appendRows($invalidCsv, $validCsv));
            self::assertSame('failed', $progress->getState(), $this->importResult($progress));
            self::assertStringContainsString('exceeds the 255 character limit', $this->invalidRecordsCsv($progress));

            $parent = $this->product($productNumber, $context);
            self::assertCount(0, $parent->getCategories() ?? []);
            self::assertSame(1190.0, $parent->getPrice()?->first()?->getGross());
            self::assertNull($this->productRepository()->search((new Criteria())->addFilter(new EqualsFilter('productNumber', $productNumber.'-1')), $context)->first());
            self::assertSame($validParentId, $this->product($validProductNumber.'-1', $context)->getParentId());
        } finally {
            $this->productRepository()->delete([['id' => $validParentId]], $context);
            $this->deleteFixture($parentId, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $context);
        }
    }

    public function testItImportsAndReconcilesOnlyCatalogRelations(): void
    {
        $context = Context::createDefaultContext();
        $suffix = bin2hex(random_bytes(5));
        $productNumber = 'CATALOG-'.$suffix;
        $parentId = Uuid::randomHex();
        $categoryGroupId = 'group-'.$suffix;
        $categoryId = 'category-'.$suffix;
        $propertyGroupId = CatalogIdentity::propertyGroupId('Color '.$suffix);
        $manualGroupId = Uuid::randomHex();
        $manualOptionId = Uuid::randomHex();
        $this->createFixture($parentId, $productNumber, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $manualOptionId, $context);
        $profileId = CatalogProductImportProfile::definition()['id'];
        $this->profileRepository()->upsert([CatalogProductImportProfile::definition()], $context);

        try {
            $first = $this->import($profileId, $this->catalogCsv($productNumber, '4260174423463', $categoryId, $categoryGroupId, 1200, 'Brown'));
            self::assertSame('succeeded', $first->getState(), $this->importResult($first));

            $child = $this->product($productNumber.'-1', $context);
            self::assertSame($parentId, $child->getParentId());
            self::assertSame('4260174423463', $child->getEan());
            self::assertSame(1200.0, $child->getPrice()?->first()?->getGross());
            self::assertCount(1, $child->getOptions() ?? []);

            $second = $this->import($profileId, $this->catalogCsv($productNumber, '4260174423464', $categoryId, $categoryGroupId, 1300, 'Black'));
            self::assertSame('succeeded', $second->getState(), $this->importResult($second));

            $reloadedChild = $this->product($productNumber.'-1', $context);
            self::assertSame($child->getId(), $reloadedChild->getId());
            self::assertSame('4260174423464', $reloadedChild->getEan());
            self::assertSame(1300.0, $reloadedChild->getPrice()?->first()?->getGross());
            self::assertCount(1, $reloadedChild->getOptions() ?? []);
            self::assertSame('Black', $reloadedChild->getOptions()?->first()?->getName());

            $parent = $this->product($productNumber, $context);
            self::assertCount(1, $parent->getProperties() ?? []);
            self::assertSame($manualOptionId, $parent->getProperties()?->first()?->getId());
        } finally {
            $this->deleteFixture($parentId, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $context);
        }
    }

    private function deleteFixture(string $parentId, string $categoryGroupId, string $categoryId, string $propertyGroupId, string $manualGroupId, Context $context): void
    {
        $this->productRepository()->delete([['id' => $parentId]], $context);
        $mappingIds = $this->catalogMappingRepository()->searchIds((new Criteria())->addFilter(new EqualsFilter('categoryGroupId', $categoryGroupId)), $context)->getIds();
        $this->catalogMappingRepository()->delete(array_map(static fn (string $id): array => ['id' => $id], $mappingIds), $context);
        $this->categoryRepository()->delete([['id' => CatalogIdentity::categoryId('okb', $categoryId)], ['id' => CatalogIdentity::categoryGroupId('okb', $categoryGroupId)]], $context);
        $this->propertyGroupRepository()->delete([['id' => $propertyGroupId], ['id' => $manualGroupId]], $context);
    }

    private function createFixture(string $parentId, string $productNumber, string $categoryGroupId, string $categoryId, string $propertyGroupId, string $manualGroupId, string $manualOptionId, Context $context): void
    {
        $taxId = $this->taxRepository()->searchIds((new Criteria())->setLimit(1), $context)->firstId();
        self::assertNotNull($taxId);
        $this->categoryRepository()->create([
            ['id' => CatalogIdentity::categoryGroupId('okb', $categoryGroupId), 'name' => 'Group '.$categoryGroupId, 'type' => 'page', 'active' => true],
            ['id' => CatalogIdentity::categoryId('okb', $categoryId), 'parentId' => CatalogIdentity::categoryGroupId('okb', $categoryGroupId), 'name' => 'Category '.$categoryId, 'type' => 'page', 'active' => true],
        ], $context);
        $this->propertyGroupRepository()->create([
            ['id' => $propertyGroupId, 'name' => 'Color '.$categoryGroupId, 'displayType' => 'text'],
            ['id' => $manualGroupId, 'name' => 'Manual '.$categoryGroupId, 'displayType' => 'text', 'options' => [['id' => $manualOptionId, 'name' => 'Retain me']]],
        ], $context);
        $this->catalogMappingRepository()->create([[
            'id' => Uuid::randomHex(),
            'sourceCode' => 'okb',
            'categoryGroupId' => $categoryGroupId,
            'categoryId' => CatalogIdentity::categoryGroupId('okb', $categoryGroupId),
            'categoryVersionId' => Defaults::LIVE_VERSION,
            'attributeId' => 'color-'.$categoryGroupId,
            'attributeName' => 'Color '.$categoryGroupId,
            'attributeType' => 'TEXT',
            'featureRelevance' => 'VARIATION_THEME',
            'multiValue' => false,
            'active' => true,
            'enabled' => true,
            'storage' => 'property',
            'propertyGroupId' => $propertyGroupId,
        ]], $context);
        $this->productRepository()->create([[
            'id' => $parentId,
            'productNumber' => $productNumber,
            'name' => 'Catalog parent',
            'stock' => 4,
            'taxId' => $taxId,
            'price' => [['currencyId' => Defaults::CURRENCY, 'net' => 1000.0, 'gross' => 1190.0, 'linked' => false]],
            'properties' => [['id' => $manualOptionId]],
        ]], $context);
    }

    private function createParent(string $id, string $productNumber, Context $context): void
    {
        $taxId = $this->taxRepository()->searchIds((new Criteria())->setLimit(1), $context)->firstId();
        self::assertNotNull($taxId);
        $this->productRepository()->create([[
            'id' => $id,
            'productNumber' => $productNumber,
            'name' => 'Catalog parent',
            'stock' => 4,
            'taxId' => $taxId,
            'price' => [['currencyId' => Defaults::CURRENCY, 'net' => 1000.0, 'gross' => 1190.0, 'linked' => false]],
        ]], $context);
    }

    private function catalogCsv(string $productNumber, string $ean, string $categoryId, string $categoryGroupId, int $price, string $value): string
    {
        $attributes = json_encode([['Color '.$categoryGroupId, [$value]]], JSON_THROW_ON_ERROR);
        $rows = [['record_type', 'product_number', 'ean', 'category_id', 'category_group_id', 'standard_price_amount', 'currency', 'attributes_json']];
        foreach (['parent', 'child'] as $recordType) {
            $rows[] = [$recordType, $productNumber, $ean, $categoryId, $categoryGroupId, (string) $price, 'EUR', $attributes];
        }
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '\\');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    private function appendRows(string $first, string $second): string
    {
        $firstRows = explode("\n", trim($first));
        $secondRows = explode("\n", trim($second));

        return implode("\n", [...$firstRows, ...array_slice($secondRows, 1)])."\n";
    }

    private function product(string $productNumber, Context $context): \Shopware\Core\Content\Product\ProductEntity
    {
        $product = $this->productRepository()->search((new Criteria())->addFilter(new EqualsFilter('productNumber', $productNumber))->addAssociation('price')->addAssociation('options')->addAssociation('properties')->addAssociation('categories'), $context)->first();
        self::assertInstanceOf(\Shopware\Core\Content\Product\ProductEntity::class, $product);

        return $product;
    }

    /** @return EntityRepository<ProductCollection> */
    private function productRepository(): EntityRepository
    {
        return static::getContainer()->get('product.repository');
    }

    /** @return EntityRepository<CategoryCollection> */
    private function categoryRepository(): EntityRepository
    {
        return static::getContainer()->get('category.repository');
    }

    /** @return EntityRepository<PropertyGroupCollection> */
    private function propertyGroupRepository(): EntityRepository
    {
        return static::getContainer()->get('property_group.repository');
    }

    /** @return EntityRepository<CatalogCategoryAttributeCollection> */
    private function catalogMappingRepository(): EntityRepository
    {
        return static::getContainer()->get('jv_catalog_category_attribute.repository');
    }

    /** @return EntityRepository<TaxCollection> */
    private function taxRepository(): EntityRepository
    {
        return static::getContainer()->get('tax.repository');
    }

    /** @return EntityRepository<EntityCollection<ImportExportProfileEntity>> */
    private function profileRepository(): EntityRepository
    {
        return static::getContainer()->get('import_export_profile.repository');
    }
}
