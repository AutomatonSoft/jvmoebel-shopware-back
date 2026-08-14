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
        if ($existing->ean !== $prepared->ean) {
            throw new \InvalidArgumentException('Prepared product EAN does not match the existing Shopware product EAN.');
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

        [$propertyOptionIds, $customFields] = $this->attributes($existing, $prepared, $schemasByName);
        [$priceGross, $priceNet] = $this->price($existing, $prepared);

        return new CatalogProductUpdate(
            $existing->id,
            [CatalogIdentity::categoryId($prepared->sourceCode, $prepared->categoryId)],
            $propertyOptionIds,
            $customFields,
            $priceGross,
            $priceNet,
        );
    }

    /**
     * @param array<string, CatalogCategoryAttributeSchema> $schemasByName
     *
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    private function attributes(ExistingProductForCatalogEnrichment $existing, CatalogProductData $prepared, array $schemasByName): array
    {
        $propertyOptionIds = array_fill_keys($existing->propertyOptionIds, true);
        $customFields = $existing->customFields;
        $catalogValues = $customFields['jv_catalog_attributes'] ?? [];
        if (!is_array($catalogValues)) {
            throw new \InvalidArgumentException('Existing jv_catalog_attributes value must be an object.');
        }

        foreach ($prepared->attributes as $attribute) {
            $schema = $schemasByName[$attribute->name] ?? null;
            if (null === $schema) {
                throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" is not present in category group %s.', $attribute->name, $prepared->categoryGroupId));
            }
            if (!$schema->active || !$schema->enabled || 'ignore' === $schema->storage) {
                continue;
            }
            $values = $this->values($attribute, $schema);
            if ([] === $values) {
                continue;
            }
            if ('property' === $schema->storage) {
                if (null === $schema->propertyGroupId) {
                    throw new \InvalidArgumentException(sprintf('Catalog property attribute "%s" has no Shopware property group.', $schema->attributeName));
                }
                foreach ($attribute->values as $value) {
                    $propertyOptionIds[CatalogIdentity::propertyOptionId($schema->propertyGroupId, $value)] = true;
                }

                continue;
            }
            if ('custom_field' !== $schema->storage) {
                throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" has unsupported storage "%s".', $schema->attributeName, $schema->storage));
            }
            $catalogValues[$schema->sourceCode.':'.$schema->attributeId] = $schema->multiValue ? $values : $values[0];
        }
        if ([] !== $catalogValues) {
            $customFields['jv_catalog_attributes'] = $catalogValues;
        }

        return [array_keys($propertyOptionIds), $customFields];
    }

    /** @return list<string|float|int> */
    private function values(CatalogProductAttribute $attribute, CatalogCategoryAttributeSchema $schema): array
    {
        if (!$schema->multiValue && 1 < count($attribute->values)) {
            throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" does not accept multiple values.', $schema->attributeName));
        }

        return array_map(function (string $value) use ($schema): string|float|int {
            return match ($schema->attributeType) {
                'STRING' => $value,
                'INTEGER' => $this->integer($value, $schema),
                'FLOAT' => $this->float($value, $schema),
                default => throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" has unsupported type "%s".', $schema->attributeName, $schema->attributeType)),
            };
        }, $attribute->values);
    }

    private function integer(string $value, CatalogCategoryAttributeSchema $schema): int
    {
        if (!preg_match('/^-?\d+$/D', $value)) {
            throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" requires an integer value.', $schema->attributeName));
        }

        return (int) $value;
    }

    private function float(string $value, CatalogCategoryAttributeSchema $schema): float
    {
        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" requires a decimal value.', $schema->attributeName));
        }

        return (float) $normalized;
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
