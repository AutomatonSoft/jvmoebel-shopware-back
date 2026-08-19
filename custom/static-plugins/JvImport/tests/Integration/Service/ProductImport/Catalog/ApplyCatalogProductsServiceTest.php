<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\Service\ProductImport\Catalog;

use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\ProductImport\Catalog\ApplyCatalogProductsService;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogCategoryAttributeSchema;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductAttribute;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductData;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;

final class ApplyCatalogProductsServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItAssignsCategoriesAllPropertiesPricesAndVariantParents(): void
    {
        $context = Context::createDefaultContext();
        $source = 'catalog-test';
        $categoryId = CatalogIdentity::categoryId($source, 'category-1');
        $groupId = CatalogIdentity::categoryGroupId($source, 'group-1');
        $propertyGroupId = Uuid::randomHex();
        $widthGroupId = Uuid::randomHex();
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $parentId = CatalogIdentity::variantParentId($source, 'model-1');
        $taxId = $this->taxId($context);
        $products = $this->products();

        $this->categories()->upsert([
            ['id' => $groupId, 'name' => 'Test group', 'active' => true],
            ['id' => $categoryId, 'parentId' => $groupId, 'name' => 'Test category', 'active' => true],
        ], $context);
        $this->propertyGroups()->create([
            ['id' => $propertyGroupId, 'name' => 'Color', 'displayType' => 'text', 'sortingType' => 'alphanumeric'],
            ['id' => $widthGroupId, 'name' => 'Width', 'displayType' => 'text', 'sortingType' => 'alphanumeric'],
        ], $context);
        $products->create([
            $this->product($firstId, '4260454043503', 'First', $taxId, 1000.0, 1500.0),
            $this->product($secondId, '4260454043504', 'Second', $taxId, 1300.0),
        ], $context);

        try {
            $service = static::getContainer()->get(ApplyCatalogProductsService::class);
            self::assertInstanceOf(ApplyCatalogProductsService::class, $service);
            $result = $service->execute([
                new CatalogProductData($source, '4260454043503', '4260454043503', 'model-1', 'category-1', 'group-1', 1200.0, 'EUR', [new CatalogProductAttribute('Color', ['Brown']), new CatalogProductAttribute('Width', ['120.5'])]),
                new CatalogProductData($source, '4260454043504', '4260454043504', 'model-1', 'category-1', 'group-1', 1100.0, 'EUR', [new CatalogProductAttribute('Color', ['White']), new CatalogProductAttribute('Width', ['140'])]),
            ], [
                new CatalogCategoryAttributeSchema($source, 'group-1', 'color', 'Color', 'STRING', false, 'property', $propertyGroupId, true, true, 'VARIATION_THEME'),
                new CatalogCategoryAttributeSchema($source, 'group-1', 'width', 'Width', 'FLOAT', false, 'property', $widthGroupId),
            ], false, $context);

            self::assertSame(2, $result->products);
            self::assertSame(4, $result->propertyOptions);
            self::assertSame(1, $result->variantParents);
            $first = $this->productById($firstId, $context);
            self::assertSame($parentId, $first->getParentId());
            self::assertContains($categoryId, $first->getCategoryIds() ?? []);
            self::assertSame(1200.0, $first->getPrice()->getCurrencyPrice(Defaults::CURRENCY, false)->getGross());
            self::assertSame(1500.0, $first->getPrice()->getCurrencyPrice(Defaults::CURRENCY, false)->getListPrice()?->getGross());
            self::assertArrayNotHasKey('jv_catalog_attributes', $first->getCustomFields() ?? []);
            self::assertCount(1, $first->getOptionIds() ?? []);
            self::assertCount(2, $first->getPropertyIds() ?? []);

            $second = $this->productById($secondId, $context);
            self::assertSame(1300.0, $second->getPrice()?->getCurrencyPrice(Defaults::CURRENCY, false)?->getGross());
            $parent = $this->productById($parentId, $context);
            self::assertSame('model-1', $parent->getProductNumber());
            self::assertSame(1300.0, $parent->getPrice()?->getCurrencyPrice(Defaults::CURRENCY, false)?->getGross());
            self::assertCount(2, $parent->getConfiguratorSettings() ?? []);
        } finally {
            $products->delete([['id' => $firstId], ['id' => $secondId], ['id' => $parentId]], $context);
            $this->propertyGroups()->delete([['id' => $propertyGroupId], ['id' => $widthGroupId]], $context);
            $this->categories()->delete([['id' => $categoryId], ['id' => $groupId]], $context);
        }
    }

    public function testItRejectsTheWholeVariantGroupWhenOneChildHasAnInvalidAttributeValue(): void
    {
        $context = Context::createDefaultContext();
        $source = 'catalog-invalid-variant-test';
        $categoryId = CatalogIdentity::categoryId($source, 'category-1');
        $groupId = CatalogIdentity::categoryGroupId($source, 'group-1');
        $propertyGroupId = Uuid::randomHex();
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $thirdId = Uuid::randomHex();
        $taxId = $this->taxId($context);
        $products = $this->products();

        $this->categories()->upsert([
            ['id' => $groupId, 'name' => 'Test group', 'active' => true],
            ['id' => $categoryId, 'parentId' => $groupId, 'name' => 'Test category', 'active' => true],
        ], $context);
        $this->propertyGroups()->create([
            ['id' => $propertyGroupId, 'name' => 'Brand information', 'displayType' => 'text', 'sortingType' => 'alphanumeric'],
        ], $context);
        $products->create([
            $this->product($firstId, '4260454043510', 'Valid child', $taxId, 1000.0),
            $this->product($secondId, '4260454043511', 'Invalid child', $taxId, 1000.0),
            $this->product($thirdId, '4260454043512', 'Valid standalone product', $taxId, 1000.0),
        ], $context);

        try {
            $service = static::getContainer()->get(ApplyCatalogProductsService::class);
            self::assertInstanceOf(ApplyCatalogProductsService::class, $service);
            $result = $service->execute([
                new CatalogProductData($source, '4260454043510', '4260454043510', 'model-invalid', 'category-1', 'group-1', 1000.0, 'EUR', [new CatalogProductAttribute('Brand information', ['Valid'])]),
                new CatalogProductData($source, '4260454043511', '4260454043511', 'model-invalid', 'category-1', 'group-1', 1000.0, 'EUR', [new CatalogProductAttribute('Brand information', [str_repeat('x', 256)])]),
                new CatalogProductData($source, '4260454043512', '4260454043512', '4260454043512', 'category-1', 'group-1', 1000.0, 'EUR', [new CatalogProductAttribute('Brand information', ['Valid'])]),
            ], [
                new CatalogCategoryAttributeSchema($source, 'group-1', 'brand-information', 'Brand information', 'STRING', false, 'property', $propertyGroupId),
            ], false, $context);

            self::assertSame(1, $result->products);
            self::assertSame(1, $result->propertyOptions);
            self::assertSame(0, $result->variantParents);
            self::assertCount(2, $result->invalidRecords);
            self::assertStringContainsString('255 character limit', $result->invalidRecords[0]->reason);
            self::assertStringContainsString('Variant group', $result->invalidRecords[1]->reason);
            self::assertNull($this->productById($firstId, $context)->getParentId());
            self::assertNull($this->productById($secondId, $context)->getParentId());
            $third = $this->productById($thirdId, $context);
            self::assertContains($categoryId, $third->getCategoryIds() ?? []);
            self::assertCount(1, $third->getPropertyIds() ?? []);
        } finally {
            $products->delete([
                ['id' => $firstId],
                ['id' => $secondId],
                ['id' => $thirdId],
                ['id' => CatalogIdentity::variantParentId($source, 'model-invalid')],
            ], $context);
            $this->propertyGroups()->delete([['id' => $propertyGroupId]], $context);
            $this->categories()->delete([['id' => $categoryId], ['id' => $groupId]], $context);
        }
    }

    /** @return EntityRepository<ProductCollection> */
    private function products(): EntityRepository
    {
        return static::getContainer()->get('product.repository');
    }

    /** @return EntityRepository<CategoryCollection> */
    private function categories(): EntityRepository
    {
        return static::getContainer()->get('category.repository');
    }

    /** @return EntityRepository<PropertyGroupCollection> */
    private function propertyGroups(): EntityRepository
    {
        return static::getContainer()->get('property_group.repository');
    }

    private function taxId(Context $context): string
    {
        /** @var EntityRepository<TaxCollection> $taxes */
        $taxes = static::getContainer()->get('tax.repository');
        $id = $taxes->searchIds((new Criteria())->setLimit(1), $context)->firstId();
        self::assertNotNull($id);

        return $id;
    }

    /** @return array<string, mixed> */
    private function product(string $id, string $productNumber, string $name, string $taxId, float $gross, ?float $listPriceGross = null): array
    {
        return [
            'id' => $id,
            'productNumber' => $productNumber,
            'ean' => $productNumber,
            'name' => $name,
            'stock' => 1,
            'taxId' => $taxId,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'net' => round($gross / 1.19, 2),
                'gross' => $gross,
                'linked' => false,
                ...null === $listPriceGross ? [] : ['listPrice' => ['net' => round($listPriceGross / 1.19, 2), 'gross' => $listPriceGross, 'linked' => false]],
            ]],
        ];
    }

    private function productById(string $id, Context $context): ProductEntity
    {
        $product = $this->products()->search(
            (new Criteria([$id]))->addAssociation('price')->addAssociation('configuratorSettings'),
            $context,
        )->first();
        self::assertInstanceOf(ProductEntity::class, $product);

        return $product;
    }
}
