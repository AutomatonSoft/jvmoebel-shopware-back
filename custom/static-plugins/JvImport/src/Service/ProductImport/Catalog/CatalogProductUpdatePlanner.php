<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogCategoryAttributeSchema;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductAttribute;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductData;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductUpdate;
use Jv\Import\Service\ProductImport\Catalog\Dto\ExistingProductForCatalogEnrichment;

final class CatalogProductUpdatePlanner
{
    /** @param list<CatalogCategoryAttributeSchema> $schemas */
    public function plan(ExistingProductForCatalogEnrichment $existing, CatalogProductData $prepared, array $schemas): CatalogProductUpdate
    {
        if ($existing->productNumber !== $prepared->productNumber) {
            throw new \InvalidArgumentException('Prepared product number does not match the existing Shopware product.');
        }

        $schemasByName = [];
        foreach ($schemas as $schema) {
            if ($schema->sourceCode !== $prepared->sourceCode || $schema->categoryGroupId !== $prepared->categoryGroupId) {
                continue;
            }
            if (isset($schemasByName[$schema->attributeName])) {
                throw new \InvalidArgumentException(sprintf('Catalog category group %s has ambiguous attribute "%s".', $prepared->categoryGroupId, $schema->attributeName));
            }
            $schemasByName[$schema->attributeName] = $schema;
        }

        [$propertyOptionIds, $variantOptionIds, $customFields] = $this->attributes($existing, $prepared, $schemasByName);
        [$priceGross, $priceNet] = $this->price($existing, $prepared);

        return new CatalogProductUpdate(
            $existing->id,
            array_keys(array_fill_keys([...$existing->categoryIds, CatalogIdentity::categoryId($prepared->sourceCode, $prepared->categoryId)], true)),
            $propertyOptionIds,
            $variantOptionIds,
            $customFields,
            $priceGross,
            $priceNet,
        );
    }

    /**
     * @param array<string, CatalogCategoryAttributeSchema> $schemasByName
     *
     * @return array{0: list<string>, 1: list<string>, 2: array<string, mixed>}
     */
    private function attributes(ExistingProductForCatalogEnrichment $existing, CatalogProductData $prepared, array $schemasByName): array
    {
        $propertyOptionIds = [];
        $variantOptionIds = [];
        $customFields = $existing->customFields;
        unset($customFields['jv_catalog_attributes']);

        foreach ($prepared->attributes as $attribute) {
            $schema = $schemasByName[$attribute->name] ?? null;
            if (null === $schema) {
                continue;
            }
            if (!$schema->active || !$schema->enabled) {
                continue;
            }
            $values = $this->values($attribute, $schema);
            if ([] === $values) {
                continue;
            }
            if ('property' !== $schema->storage || null === $schema->propertyGroupId) {
                throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" has no Shopware property group.', $schema->attributeName));
            }
            foreach ($values as $value) {
                $optionId = CatalogIdentity::propertyOptionId($schema->propertyGroupId, $value);
                $propertyOptionIds[$optionId] = true;
                if (str_contains((string) $schema->featureRelevance, 'VARIATION_THEME')) {
                    $variantOptionIds[$optionId] = true;
                }
            }
        }

        return [array_keys($propertyOptionIds), array_keys($variantOptionIds), $customFields];
    }

    /** @return list<string> */
    private function values(CatalogProductAttribute $attribute, CatalogCategoryAttributeSchema $schema): array
    {
        if (!$schema->multiValue && 1 < count($attribute->values)) {
            throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" does not accept multiple values.', $schema->attributeName));
        }
        foreach ($attribute->values as $value) {
            if (255 < mb_strlen($value)) {
                throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" value exceeds the 255 character limit for a Shopware property option.', $schema->attributeName));
            }
        }

        return $attribute->values;
    }

    /** @return array{0: float, 1: float} */
    private function price(ExistingProductForCatalogEnrichment $existing, CatalogProductData $prepared): array
    {
        if (null === $prepared->standardPriceAmount) {
            return [$existing->priceGross, $existing->priceNet];
        }
        if (null === $prepared->currency || strtoupper($existing->currencyCode) !== strtoupper($prepared->currency)) {
            throw new \InvalidArgumentException('Catalog price currency does not match the existing Shopware price currency.');
        }
        if ($prepared->standardPriceAmount <= $existing->priceGross) {
            return [$existing->priceGross, $existing->priceNet];
        }

        $gross = $prepared->standardPriceAmount;

        return [$gross, round($gross / (1 + $existing->taxRate / 100), 2)];
    }
}
