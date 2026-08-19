<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\Catalog;

use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\ProductImport\Catalog\CatalogProductUpdatePlanner;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogCategoryAttributeSchema;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductAttribute;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductData;
use Jv\Import\Service\ProductImport\Catalog\Dto\ExistingProductForCatalogEnrichment;
use PHPUnit\Framework\TestCase;

final class CatalogProductUpdatePlannerTest extends TestCase
{
    public function testItPlansAllOkbAttributesAsPropertiesAndRemovesLegacyCatalogJson(): void
    {
        $update = (new CatalogProductUpdatePlanner())->plan(
            new ExistingProductForCatalogEnrichment(
                'product-id',
                '4260454043503',
                '4260454043503',
                'EUR',
                1000.0,
                840.34,
                19.0,
                ['existing-option'],
                [
                    'jv_internal_article_code' => 'Sofort',
                    'jv_catalog_attributes' => ['okb:101' => 120.5],
                ],
            ),
            new CatalogProductData(
                'okb',
                '4260454043503',
                '4260454043503',
                'reference-1',
                '25922',
                '3446',
                1200.0,
                'EUR',
                [
                    new CatalogProductAttribute('Farbe', ['Braun']),
                    new CatalogProductAttribute('Breite', ['120.5']),
                    new CatalogProductAttribute('Lieferumfang', ['Kissen', 'Decke']),
                ],
            ),
            [
                new CatalogCategoryAttributeSchema('okb', '3446', '100', 'Farbe', 'STRING', false, 'property', 'farbe-group'),
                new CatalogCategoryAttributeSchema('okb', '3446', '101', 'Breite', 'FLOAT', false, 'property', 'breite-group'),
                new CatalogCategoryAttributeSchema('okb', '3446', '102', 'Lieferumfang', 'STRING', true, 'property', 'lieferumfang-group'),
            ],
        );

        self::assertSame('product-id', $update->productId);
        self::assertSame([CatalogIdentity::categoryId('okb', '25922')], $update->categoryIds);
        self::assertSame([
            'existing-option',
            CatalogIdentity::propertyOptionId('farbe-group', 'Braun'),
            CatalogIdentity::propertyOptionId('breite-group', '120.5'),
            CatalogIdentity::propertyOptionId('lieferumfang-group', 'Kissen'),
            CatalogIdentity::propertyOptionId('lieferumfang-group', 'Decke'),
        ], $update->propertyOptionIds);
        self::assertSame([], $update->variantOptionIds);
        self::assertSame(['jv_internal_article_code' => 'Sofort'], $update->customFields);
        self::assertSame(1200.0, $update->priceGross);
        self::assertSame(1008.4, $update->priceNet);
    }

    public function testItKeepsTheHigherExistingCosmoShopPrice(): void
    {
        $update = (new CatalogProductUpdatePlanner())->plan(
            new ExistingProductForCatalogEnrichment('product-id', '4260454043503', '4260454043503', 'EUR', 1500.0, 1260.5, 19.0, [], []),
            new CatalogProductData('okb', '4260454043503', '4260454043503', 'reference-1', '25922', '3446', 1200.0, 'EUR', []),
            [],
        );

        self::assertSame(1500.0, $update->priceGross);
        self::assertSame(1260.5, $update->priceNet);
    }

    public function testItRejectsAProductWithAnotherEan(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EAN does not match');

        (new CatalogProductUpdatePlanner())->plan(
            new ExistingProductForCatalogEnrichment('product-id', '4260454043503', '4260454043504', 'EUR', 1000.0, 840.34, 19.0, [], []),
            new CatalogProductData('okb', '4260454043503', '4260454043503', 'reference-1', '25922', '3446', 1200.0, 'EUR', []),
            [],
        );
    }

    public function testItRejectsAnUnknownAttributeInsteadOfSilentlyDroppingIt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not present in category group 3446');

        (new CatalogProductUpdatePlanner())->plan(
            new ExistingProductForCatalogEnrichment('product-id', '4260454043503', '4260454043503', 'EUR', 1000.0, 840.34, 19.0, [], []),
            new CatalogProductData('okb', '4260454043503', '4260454043503', 'reference-1', '25922', '3446', 1200.0, 'EUR', [new CatalogProductAttribute('Unknown', ['x'])]),
            [],
        );
    }

    public function testItRejectsACatalogPriceInAnotherCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('currency does not match');

        (new CatalogProductUpdatePlanner())->plan(
            new ExistingProductForCatalogEnrichment('product-id', '4260454043503', '4260454043503', 'EUR', 1000.0, 840.34, 19.0, [], []),
            new CatalogProductData('okb', '4260454043503', '4260454043503', 'reference-1', '25922', '3446', 1200.0, 'CHF', []),
            [],
        );
    }

    public function testItRejectsSeveralValuesForASingleValueAttribute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not accept multiple values');

        (new CatalogProductUpdatePlanner())->plan(
            new ExistingProductForCatalogEnrichment('product-id', '4260454043503', '4260454043503', 'EUR', 1000.0, 840.34, 19.0, [], []),
            new CatalogProductData('okb', '4260454043503', '4260454043503', 'reference-1', '25922', '3446', null, null, [new CatalogProductAttribute('Breite', ['120', '130'])]),
            [new CatalogCategoryAttributeSchema('okb', '3446', '101', 'Breite', 'FLOAT', false, 'property', 'breite-group')],
        );
    }

    public function testItRejectsAPropertyOptionValueLongerThanShopwareAllows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the 255 character limit');

        (new CatalogProductUpdatePlanner())->plan(
            new ExistingProductForCatalogEnrichment('product-id', '4260454043503', '4260454043503', 'EUR', 1000.0, 840.34, 19.0, [], []),
            new CatalogProductData('okb', '4260454043503', '4260454043503', 'reference-1', '25922', '3446', null, null, [new CatalogProductAttribute('Markeninformationen', [str_repeat('x', 256)])]),
            [new CatalogCategoryAttributeSchema('okb', '3446', '101', 'Markeninformationen', 'STRING', false, 'property', 'brand-information-group')],
        );
    }

    public function testItDoesNotImportDisabledOrInactiveMappedAttributes(): void
    {
        $update = (new CatalogProductUpdatePlanner())->plan(
            new ExistingProductForCatalogEnrichment('product-id', '4260454043503', '4260454043503', 'EUR', 1000.0, 840.34, 19.0, [], []),
            new CatalogProductData('okb', '4260454043503', '4260454043503', 'reference-1', '25922', '3446', null, null, [
                new CatalogProductAttribute('Disabled', ['do not use']),
                new CatalogProductAttribute('Missing at source', ['do not use']),
            ]),
            [
                new CatalogCategoryAttributeSchema('okb', '3446', '101', 'Disabled', 'STRING', false, 'property', 'disabled-group', true, false),
                new CatalogCategoryAttributeSchema('okb', '3446', '102', 'Missing at source', 'STRING', false, 'property', 'missing-group', false, true),
            ],
        );

        self::assertSame([], $update->propertyOptionIds);
        self::assertSame([], $update->customFields);
    }

    public function testItUsesVariationThemePropertiesAsChildVariantOptions(): void
    {
        $update = (new CatalogProductUpdatePlanner())->plan(
            new ExistingProductForCatalogEnrichment('product-id', '4260454043503', '4260454043503', 'EUR', 1000.0, 840.34, 19.0, [], []),
            new CatalogProductData('okb', '4260454043503', '4260454043503', 'reference-1', '25922', '3446', null, null, [new CatalogProductAttribute('Color', ['Brown'])]),
            [new CatalogCategoryAttributeSchema('okb', '3446', '100', 'Color', 'STRING', false, 'property', 'color-group', true, true, 'TITLE|VARIATION_THEME|SEARCH')],
        );

        $optionId = CatalogIdentity::propertyOptionId('color-group', 'Brown');
        self::assertSame([$optionId], $update->propertyOptionIds);
        self::assertSame([$optionId], $update->variantOptionIds);
    }
}
