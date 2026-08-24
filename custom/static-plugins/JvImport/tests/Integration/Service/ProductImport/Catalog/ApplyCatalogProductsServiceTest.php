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
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Tax\TaxCollection;

final class ApplyCatalogProductsServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItTurnsTheExistingCosmoShopProductIntoAParentAndCreatesItsOkbChild(): void
    {
        $context = Context::createDefaultContext();
        $source = 'catalog-test';
        $categoryId = CatalogIdentity::categoryId($source, 'category-1');
        $groupId = CatalogIdentity::categoryGroupId($source, 'group-1');
        $propertyGroupId = Uuid::randomHex();
        $widthGroupId = Uuid::randomHex();
        $legacyGroupId = Uuid::randomHex();
        $legacyOptionId = Uuid::randomHex();
        $legacyConfiguratorSettingId = Uuid::randomHex();
        $firstId = Uuid::randomHex();
        $englishLanguageId = Uuid::randomHex();
        $taxId = $this->taxId($context);
        $products = $this->products();
        $child = null;

        $englishLocaleId = $this->localeId('en-GB', $context);
        $this->languages()->create([[
            'id' => $englishLanguageId,
            'name' => 'Catalog import English',
            'localeId' => $englishLocaleId,
            'translationCodeId' => $englishLocaleId,
        ]], $context);

        $this->categories()->upsert([
            ['id' => $groupId, 'name' => 'Test group', 'active' => true],
            ['id' => $categoryId, 'parentId' => $groupId, 'name' => 'Test category', 'active' => true],
        ], $context);
        $this->propertyGroups()->create([
            ['id' => $propertyGroupId, 'name' => 'Color', 'displayType' => 'text', 'sortingType' => 'alphanumeric'],
            ['id' => $widthGroupId, 'name' => 'Width', 'displayType' => 'text', 'sortingType' => 'alphanumeric'],
            ['id' => $legacyGroupId, 'name' => 'Legacy', 'displayType' => 'text', 'sortingType' => 'alphanumeric', 'options' => [['id' => $legacyOptionId, 'name' => 'Old value']]],
        ], $context);
        $products->create([
            [
                ...$this->product($firstId, 'cosmo-123', 'First', $taxId, 1000.0, 1500.0),
                'properties' => [['id' => $legacyOptionId]],
                'configuratorSettings' => [['id' => $legacyConfiguratorSettingId, 'optionId' => $legacyOptionId]],
            ],
        ], $context);

        try {
            $service = static::getContainer()->get(ApplyCatalogProductsService::class);
            self::assertInstanceOf(ApplyCatalogProductsService::class, $service);
            $result = $service->execute([
                new CatalogProductData($source, 'cosmo-123', '4260454043503', 'category-1', 'group-1', 1200.0, 'EUR', [new CatalogProductAttribute('Color', ['Brown']), new CatalogProductAttribute('Width', ['120.5'])]),
            ], [
                new CatalogCategoryAttributeSchema($source, 'group-1', 'color', 'Color', 'STRING', false, 'property', $propertyGroupId, true, true, 'VARIATION_THEME'),
                new CatalogCategoryAttributeSchema($source, 'group-1', 'width', 'Width', 'FLOAT', false, 'property', $widthGroupId),
            ], false, $context);

            self::assertSame(1, $result->products);
            self::assertSame(2, $result->propertyOptions);
            $parent = $this->productById($firstId, $context);
            self::assertNull($parent->getParentId());
            self::assertNull($parent->getEan());
            self::assertContains($categoryId, $parent->getCategoryIds() ?? []);
            self::assertCount(0, $parent->getPropertyIds() ?? []);
            self::assertSame(1000.0, $parent->getPrice()->getCurrencyPrice(Defaults::CURRENCY, false)->getGross());
            self::assertSame(1500.0, $parent->getPrice()->getCurrencyPrice(Defaults::CURRENCY, false)->getListPrice()?->getGross());
            self::assertCount(1, $parent->getConfiguratorSettings() ?? []);

            $child = $this->productByNumber('cosmo-123-1', $context);
            self::assertSame($firstId, $child->getParentId());
            self::assertSame('4260454043503', $child->getEan());
            self::assertSame(1200.0, $child->getPrice()->getCurrencyPrice(Defaults::CURRENCY, false)->getGross());
            self::assertCount(1, $child->getOptionIds() ?? []);
            self::assertCount(2, $child->getPropertyIds() ?? []);
            $colorOption = $this->propertyOptions()->search((new Criteria([CatalogIdentity::propertyOptionId($propertyGroupId, 'Brown')]))->addAssociation('translations'), $context)->first();
            self::assertNotNull($colorOption);
            self::assertGreaterThanOrEqual(2, count($colorOption->getTranslations() ?? []));
        } finally {
            $records = [['id' => $firstId]];
            if ($child instanceof ProductEntity) {
                $records[] = ['id' => $child->getId()];
            }
            $products->delete($records, $context);
            $this->propertyGroups()->delete([['id' => $propertyGroupId], ['id' => $widthGroupId], ['id' => $legacyGroupId]], $context);
            $this->categories()->delete([['id' => $categoryId], ['id' => $groupId]], $context);
            $this->languages()->delete([['id' => $englishLanguageId]], $context);
        }
    }

    public function testItContinuesWithAnotherSkuWhenOneSkuHasAnInvalidAttributeValue(): void
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
        $firstChild = null;
        $thirdChild = null;

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
                new CatalogProductData($source, '4260454043510', '4260454043510', 'category-1', 'group-1', 1000.0, 'EUR', [new CatalogProductAttribute('Brand information', ['Valid'])]),
                new CatalogProductData($source, '4260454043511', '4260454043511', 'category-1', 'group-1', 1000.0, 'EUR', [new CatalogProductAttribute('Brand information', [str_repeat('x', 256)])]),
                new CatalogProductData($source, '4260454043512', '4260454043512', 'category-1', 'group-1', 1000.0, 'EUR', [new CatalogProductAttribute('Brand information', ['Valid'])]),
            ], [
                new CatalogCategoryAttributeSchema($source, 'group-1', 'brand-information', 'Brand information', 'STRING', false, 'property', $propertyGroupId),
            ], false, $context);

            self::assertSame(2, $result->products);
            self::assertSame(1, $result->propertyOptions);
            self::assertCount(1, $result->invalidRecords);
            self::assertStringContainsString('255 character limit', $result->invalidRecords[0]->reason);
            $first = $this->productById($firstId, $context);
            self::assertNull($first->getParentId());
            self::assertNull($first->getEan());
            self::assertContains($categoryId, $first->getCategoryIds() ?? []);
            self::assertNull($this->productById($secondId, $context)->getParentId());
            $third = $this->productById($thirdId, $context);
            self::assertNull($third->getEan());
            self::assertContains($categoryId, $third->getCategoryIds() ?? []);
            $firstChild = $this->productByNumber('4260454043510-1', $context);
            self::assertSame($firstId, $firstChild->getParentId());
            self::assertSame('4260454043510', $firstChild->getEan());
            self::assertCount(1, $firstChild->getPropertyIds() ?? []);
            $thirdChild = $this->productByNumber('4260454043512-1', $context);
            self::assertSame($thirdId, $thirdChild->getParentId());
            self::assertSame('4260454043512', $thirdChild->getEan());
            self::assertCount(1, $thirdChild->getPropertyIds() ?? []);
        } finally {
            $records = [
                ['id' => $firstId],
                ['id' => $secondId],
                ['id' => $thirdId],
            ];
            if ($firstChild instanceof ProductEntity) {
                $records[] = ['id' => $firstChild->getId()];
            }
            if ($thirdChild instanceof ProductEntity) {
                $records[] = ['id' => $thirdChild->getId()];
            }
            $products->delete($records, $context);
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

    /** @return EntityRepository<PropertyGroupOptionCollection> */
    private function propertyOptions(): EntityRepository
    {
        return static::getContainer()->get('property_group_option.repository');
    }

    /** @return EntityRepository<LanguageCollection> */
    private function languages(): EntityRepository
    {
        return static::getContainer()->get('language.repository');
    }

    private function localeId(string $code, Context $context): string
    {
        /** @var EntityRepository<LocaleCollection> $locales */
        $locales = static::getContainer()->get('locale.repository');
        $id = $locales->searchIds((new Criteria())->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('code', $code)), $context)->firstId();
        self::assertNotNull($id);

        return $id;
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

    private function productByNumber(string $productNumber, Context $context): ProductEntity
    {
        $product = $this->products()->search(
            (new Criteria())->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('productNumber', $productNumber))->addAssociation('price'),
            $context,
        )->first();
        self::assertInstanceOf(ProductEntity::class, $product);

        return $product;
    }
}
