<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Doctrine\DBAL\Connection;
use Jv\Import\Core\Content\CatalogCategoryAttribute\CatalogCategoryAttributeCollection;
use Jv\Import\Integration\Okb\Profile\CatalogProductImportProfile;
use Jv\Import\Service\Catalog\CatalogIdentity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
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
    public function testItFindsExactlyOneCatalogImportForAnEnrichmentSourceLog(): void
    {
        $context = Context::createDefaultContext();
        $sourceLogId = Uuid::randomHex();
        $catalogLogId = Uuid::randomHex();
        $this->profileRepository()->upsert([CatalogProductImportProfile::definition()], $context);
        /** @var EntityRepository<EntityCollection<ImportExportProfileEntity>> $logRepository */
        $logRepository = static::getContainer()->get('import_export_log.repository');
        $logRepository->create([[
            'id' => $catalogLogId,
            'activity' => 'import',
            'state' => 'progress',
            'records' => 0,
            'profileId' => CatalogProductImportProfile::definition()['id'],
            'profileName' => CatalogProductImportProfile::definition()['technicalName'],
            'config' => ['parameters' => ['jvCatalogEnrichmentSourceImportLogId' => $sourceLogId]],
        ]], $context);
        try {
            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('profileId', CatalogProductImportProfile::definition()['id']))
                ->addFilter(new EqualsFilter('config.parameters.jvCatalogEnrichmentSourceImportLogId', $sourceLogId));
            self::assertSame([$catalogLogId], $logRepository->searchIds($criteria, $context)->getIds());
        } finally {
            $logRepository->delete([['id' => $catalogLogId]], $context);
        }
    }

    public function testItKeepsExistingCatalogRelationsWhenPreparedRowsAreInvalid(): void
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
            self::assertCount(1, $parent->getCategories() ?? []);
            self::assertSame(CatalogIdentity::categoryId('okb', $categoryId), $parent->getCategories()?->first()?->getId());
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
            $replacementCategoryId = $categoryId.'-replacement';
            $replacementCategoryShopwareId = CatalogIdentity::categoryId('okb', $replacementCategoryId);
            $this->categoryRepository()->create([[
                'id' => $replacementCategoryShopwareId,
                'parentId' => CatalogIdentity::categoryGroupId('okb', $categoryGroupId),
                'name' => 'Replacement category',
                'type' => 'page',
                'active' => true,
            ]], $context);
            $first = $this->import($profileId, $this->catalogCsv($productNumber, '4260174423463', $categoryId, $categoryGroupId, 1200, 'Brown'));
            self::assertSame('succeeded', $first->getState(), $this->importResult($first));

            $child = $this->product($productNumber.'-1', $context);
            self::assertSame($parentId, $child->getParentId());
            self::assertNull($child->getDeliveryTimeId());
            self::assertSame('4260174423463', $child->getEan());
            self::assertSame(1200.0, $child->getPrice()?->first()?->getGross());
            self::assertCount(1, $child->getOptions() ?? []);
            $brownOptionId = $child->getOptions()?->first()?->getId();
            self::assertIsString($brownOptionId);
            self::assertSame(1, (int) $this->connection()->fetchOne(
                'SELECT COUNT(*) FROM `product_configurator_setting` WHERE `product_id` = :productId AND `product_version_id` = :versionId AND `property_group_option_id` = :optionId',
                ['productId' => Uuid::fromHexToBytes($parentId), 'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'optionId' => Uuid::fromHexToBytes($brownOptionId)],
            ));
            $this->connection()->executeStatement(
                'UPDATE `property_group_option_translation` SET `name` = :name WHERE `property_group_option_id` = :optionId AND `language_id` = :languageId',
                ['name' => 'Manuell übersetzt', 'optionId' => Uuid::fromHexToBytes($brownOptionId), 'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
            );

            $second = $this->import($profileId, $this->catalogCsv($productNumber, '4260174423464', $replacementCategoryId, $categoryGroupId, 1300, 'Black'));
            self::assertSame('succeeded', $second->getState(), $this->importResult($second));

            $reloadedChild = $this->product($productNumber.'-1', $context);
            self::assertSame($child->getId(), $reloadedChild->getId());
            self::assertNull($reloadedChild->getDeliveryTimeId());
            self::assertSame('4260174423464', $reloadedChild->getEan());
            self::assertSame(1300.0, $reloadedChild->getPrice()?->first()?->getGross());
            self::assertCount(1, $reloadedChild->getOptions() ?? []);
            self::assertSame('Black', $reloadedChild->getOptions()?->first()?->getName());
            self::assertSame(1, (int) $this->connection()->fetchOne(
                'SELECT COUNT(*) FROM `product_configurator_setting` WHERE `product_id` = :productId AND `product_version_id` = :versionId',
                ['productId' => Uuid::fromHexToBytes($parentId), 'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ));
            $reloadedParent = $this->product($productNumber, $context);
            self::assertSame($replacementCategoryShopwareId, $reloadedParent->getCategories()?->first()?->getId());
            self::assertNotContains(CatalogIdentity::categoryId('okb', $categoryId), $reloadedParent->getCategoryTree() ?? []);
            self::assertContains($replacementCategoryShopwareId, $reloadedParent->getCategoryTree() ?? []);
            self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM `product_category_tree` WHERE `product_id` = :productId AND `category_id` = :categoryId', ['productId' => Uuid::fromHexToBytes($parentId), 'categoryId' => Uuid::fromHexToBytes(CatalogIdentity::categoryId('okb', $categoryId))]));
            self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM `product_category_tree` WHERE `product_id` = :productId AND `category_id` = :categoryId', ['productId' => Uuid::fromHexToBytes($parentId), 'categoryId' => Uuid::fromHexToBytes($replacementCategoryShopwareId)]));

            $third = $this->import($profileId, $this->catalogCsv($productNumber, '4260174423464', $replacementCategoryId, $categoryGroupId, 1300, 'Brown'));
            self::assertSame('succeeded', $third->getState(), $this->importResult($third));
            self::assertSame('Manuell übersetzt', $this->connection()->fetchOne(
                'SELECT `name` FROM `property_group_option_translation` WHERE `property_group_option_id` = :optionId AND `language_id` = :languageId',
                ['optionId' => Uuid::fromHexToBytes($brownOptionId), 'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
            ));

            $parent = $this->product($productNumber, $context);
            self::assertCount(1, $parent->getProperties() ?? []);
            self::assertSame($manualOptionId, $parent->getProperties()?->first()?->getId());
        } finally {
            $this->deleteFixture($parentId, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $context);
        }
    }

    public function testItRejectsBothRowsWhenTheGeneratedChildProductNumberIsGlobalConflict(): void
    {
        $context = Context::createDefaultContext();
        $suffix = bin2hex(random_bytes(5));
        $productNumber = 'CATALOG-CONFLICT-'.$suffix;
        $parentId = Uuid::randomHex();
        $categoryGroupId = 'group-'.$suffix;
        $categoryId = 'category-'.$suffix;
        $propertyGroupId = CatalogIdentity::propertyGroupId('Color '.$suffix);
        $manualGroupId = Uuid::randomHex();
        $manualOptionId = Uuid::randomHex();
        $conflictId = Uuid::randomHex();
        $this->createFixture($parentId, $productNumber, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $manualOptionId, $context);
        $this->createParent($conflictId, $productNumber.'-1', $context);
        $this->profileRepository()->upsert([CatalogProductImportProfile::definition()], $context);

        try {
            $progress = $this->import(CatalogProductImportProfile::definition()['id'], $this->catalogCsv($productNumber, '4260174423463', $categoryId, $categoryGroupId, 1200, 'Brown'));
            self::assertSame('failed', $progress->getState(), $this->importResult($progress));
            self::assertStringContainsString('belongs to another product', $this->invalidRecordsCsv($progress));
            $parent = $this->product($productNumber, $context);
            self::assertSame(1190.0, $parent->getPrice()?->first()?->getGross());
            self::assertSame(CatalogIdentity::categoryId('okb', $categoryId), $parent->getCategories()?->first()?->getId());
            self::assertSame($conflictId, $this->product($productNumber.'-1', $context)->getId());
        } finally {
            $this->productRepository()->delete([['id' => $conflictId]], $context);
            $this->deleteFixture($parentId, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $context);
        }
    }

    public function testItIgnoresMarkeninformationenAndLeavesTheManufacturerDescriptionUnchanged(): void
    {
        $context = Context::createDefaultContext();
        $suffix = bin2hex(random_bytes(5));
        $productNumber = 'CATALOG-BRAND-'.$suffix;
        $parentId = Uuid::randomHex();
        $categoryGroupId = 'group-'.$suffix;
        $categoryId = 'category-'.$suffix;
        $propertyGroupId = CatalogIdentity::propertyGroupId('Color '.$suffix);
        $manualGroupId = Uuid::randomHex();
        $manualOptionId = Uuid::randomHex();
        $manufacturerId = Uuid::randomHex();
        $this->manufacturerRepository()->create([[
            'id' => $manufacturerId,
            'name' => 'JVMOEBEL '.$suffix,
            'description' => 'Handwritten manufacturer text',
        ]], $context);
        $this->createFixture($parentId, $productNumber, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $manualOptionId, $context, $manufacturerId);
        $profileId = CatalogProductImportProfile::definition()['id'];
        $this->profileRepository()->upsert([CatalogProductImportProfile::definition()], $context);

        try {
            $csv = $this->catalogCsv($productNumber, '4260174423463', $categoryId, $categoryGroupId, 1200, 'Brown', 'Marketing copy about our own brand');
            $progress = $this->import($profileId, $csv);
            self::assertSame('succeeded', $progress->getState(), $this->importResult($progress));

            $child = $this->product($productNumber.'-1', $context);
            self::assertSame($parentId, $child->getParentId());

            $manufacturer = $this->manufacturerRepository()->search(new Criteria([$manufacturerId]), $context)->first();
            self::assertInstanceOf(\Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity::class, $manufacturer);
            self::assertSame('Handwritten manufacturer text', $manufacturer->getDescription());
        } finally {
            $this->deleteFixture($parentId, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $context);
            $this->manufacturerRepository()->delete([['id' => $manufacturerId]], $context);
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

    private function createFixture(string $parentId, string $productNumber, string $categoryGroupId, string $categoryId, string $propertyGroupId, string $manualGroupId, string $manualOptionId, Context $context, ?string $manufacturerId = null): void
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
            'manufacturerId' => $manufacturerId,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'net' => 1000.0,
                'gross' => 1190.0,
                'linked' => false,
                'listPrice' => ['currencyId' => Defaults::CURRENCY, 'net' => 1500.0, 'gross' => 1785.0, 'linked' => false],
            ]],
            'categories' => [['id' => CatalogIdentity::categoryId('okb', $categoryId)]],
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

    private function catalogCsv(string $productNumber, string $ean, string $categoryId, string $categoryGroupId, int $price, string $value, ?string $brandInformation = null): string
    {
        $attributeList = [['Color '.$categoryGroupId, [$value]]];
        if (null !== $brandInformation) {
            $attributeList[] = ['Markeninformationen', [$brandInformation]];
        }
        $attributes = json_encode($attributeList, JSON_THROW_ON_ERROR);
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

    public function testTheChildKeepsTheListPriceOfItsParent(): void
    {
        $context = Context::createDefaultContext();
        $suffix = bin2hex(random_bytes(5));
        $productNumber = 'CATALOG-UVP-'.$suffix;
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
            $progress = $this->import($profileId, $this->catalogCsv($productNumber, '4260174423463', $categoryId, $categoryGroupId, 1200, 'Brown'));
            self::assertSame('succeeded', $progress->getState(), $this->importResult($progress));

            $price = $this->product($productNumber.'-1', $context)->getPrice()?->getCurrencyPrice(Defaults::CURRENCY);
            self::assertNotNull($price);
            self::assertSame(1200.0, $price->getGross());
            self::assertNotNull($price->getListPrice());
            self::assertSame(1785.0, $price->getListPrice()->getGross());
        } finally {
            $this->deleteFixture($parentId, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $context);
        }
    }

    public function testTheEnrichedParentKeepsItsSourceEan(): void
    {
        $context = Context::createDefaultContext();
        $suffix = bin2hex(random_bytes(5));
        $productNumber = 'CATALOG-EAN-'.$suffix;
        $parentId = Uuid::randomHex();
        $categoryGroupId = 'group-'.$suffix;
        $categoryId = 'category-'.$suffix;
        $propertyGroupId = CatalogIdentity::propertyGroupId('Color '.$suffix);
        $manualGroupId = Uuid::randomHex();
        $manualOptionId = Uuid::randomHex();
        $this->createFixture($parentId, $productNumber, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $manualOptionId, $context);
        $this->productRepository()->update([['id' => $parentId, 'ean' => '4260174423463']], $context);
        $profileId = CatalogProductImportProfile::definition()['id'];
        $this->profileRepository()->upsert([CatalogProductImportProfile::definition()], $context);

        try {
            $progress = $this->import($profileId, $this->catalogCsv($productNumber, '4260174423463', $categoryId, $categoryGroupId, 1200, 'Brown'));
            self::assertSame('succeeded', $progress->getState(), $this->importResult($progress));

            self::assertSame('4260174423463', $this->product($productNumber, $context)->getEan());
            self::assertSame('4260174423463', $this->product($productNumber.'-1', $context)->getEan());
        } finally {
            $this->deleteFixture($parentId, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $context);
        }
    }

    public function testAFamilySplitAcrossWorkerBatchesKeepsEveryVariantOnItsOwnNumber(): void
    {
        $context = Context::createDefaultContext();
        $suffix = bin2hex(random_bytes(5));
        $productNumber = 'CATALOG-BATCH-'.$suffix;
        $parentId = Uuid::randomHex();
        $categoryGroupId = 'group-'.$suffix;
        $categoryId = 'category-'.$suffix;
        $propertyGroupId = CatalogIdentity::propertyGroupId('Color '.$suffix);
        $manualGroupId = Uuid::randomHex();
        $manualOptionId = Uuid::randomHex();
        $this->createFixture($parentId, $productNumber, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $manualOptionId, $context);
        $profileId = CatalogProductImportProfile::definition()['id'];
        $this->profileRepository()->upsert([CatalogProductImportProfile::definition()], $context);
        $eans = array_map(static fn (int $index): string => sprintf('4260174%06d', $index), range(1, 55));

        try {
            $progress = $this->importInWorkerBatches($profileId, $this->familyCsv($productNumber, $eans, $categoryId, $categoryGroupId));
            self::assertSame('succeeded', $progress->getState(), $this->importResult($progress));

            $children = $this->productRepository()->search((new Criteria())->addFilter(new EqualsFilter('parentId', $parentId)), $context)->getEntities();
            $numbers = array_map(static fn (\Shopware\Core\Content\Product\ProductEntity $child): string => $child->getProductNumber(), array_values($children->getElements()));
            sort($numbers);
            $expectedNumbers = array_map(static fn (int $position): string => $productNumber.'-'.$position, range(1, 55));
            sort($expectedNumbers);
            self::assertSame($expectedNumbers, $numbers);
            $childEans = array_map(static fn (\Shopware\Core\Content\Product\ProductEntity $child): ?string => $child->getEan(), array_values($children->getElements()));
            sort($childEans);
            self::assertSame($eans, $childEans);
        } finally {
            $this->deleteFixture($parentId, $categoryGroupId, $categoryId, $propertyGroupId, $manualGroupId, $context);
        }
    }

    /** @param list<string> $eans */
    private function familyCsv(string $productNumber, array $eans, string $categoryId, string $categoryGroupId): string
    {
        $rows = [['record_type', 'product_number', 'ean', 'category_id', 'category_group_id', 'standard_price_amount', 'currency', 'attributes_json']];
        foreach ($eans as $index => $ean) {
            $row = [$productNumber, $ean, $categoryId, $categoryGroupId, '1200', 'EUR', json_encode([['Color '.$categoryGroupId, ['Shade '.$index]]], JSON_THROW_ON_ERROR)];
            if (0 === $index) {
                $rows[] = ['parent', ...$row];
            }
            $rows[] = ['child', ...$row];
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

    /** @return EntityRepository<ProductManufacturerCollection> */
    private function manufacturerRepository(): EntityRepository
    {
        return static::getContainer()->get('product_manufacturer.repository');
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

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
