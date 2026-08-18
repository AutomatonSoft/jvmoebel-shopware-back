<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog;

use Jv\Import\Service\Catalog\Dto\CatalogAttribute;
use Jv\Import\Service\Catalog\Dto\CatalogAttributeMapping;

final class CatalogAttributeMappingSynchronizer
{
    /**
     * @param list<CatalogAttribute>        $observedAttributes
     * @param list<CatalogAttributeMapping> $existingMappings
     *
     * @return list<CatalogAttributeMapping>
     */
    public function synchronize(string $sourceCode, array $observedAttributes, array $existingMappings): array
    {
        $existingByKey = [];
        foreach ($existingMappings as $mapping) {
            if ($mapping->sourceCode !== $sourceCode) {
                throw new \InvalidArgumentException(sprintf('Catalog attribute mapping does not belong to source "%s".', $sourceCode));
            }
            $existingByKey[$this->key($mapping->categoryGroupId, $mapping->attributeId)] = $mapping;
        }

        $synchronized = [];
        foreach ($observedAttributes as $attribute) {
            $key = $this->key($attribute->categoryGroupSourceKey, $attribute->sourceKey);
            $mapping = $existingByKey[$key] ?? null;
            $synchronized[$key] = new CatalogAttributeMapping(
                $sourceCode,
                $attribute->categoryGroupSourceKey,
                $attribute->sourceKey,
                $attribute->name,
                $attribute->type,
                $attribute->sourceRelevance,
                $attribute->multiValue,
                true,
                null === $mapping ? false : $mapping->enabled,
                null === $mapping ? $attribute->storage : $mapping->storage,
                null === $mapping ? $this->defaultPropertyGroupId($attribute) : $mapping->propertyGroupId,
                $mapping?->customFieldName,
            );
        }

        foreach ($existingByKey as $key => $mapping) {
            if (isset($synchronized[$key])) {
                continue;
            }
            $synchronized[$key] = new CatalogAttributeMapping(
                $mapping->sourceCode,
                $mapping->categoryGroupId,
                $mapping->attributeId,
                $mapping->attributeName,
                $mapping->attributeType,
                $mapping->featureRelevance,
                $mapping->multiValue,
                false,
                $mapping->enabled,
                $mapping->storage,
                $mapping->propertyGroupId,
                $mapping->customFieldName,
            );
        }

        ksort($synchronized);

        return array_values($synchronized);
    }

    private function defaultPropertyGroupId(CatalogAttribute $attribute): ?string
    {
        if ('property' !== $attribute->storage) {
            return null;
        }

        return CatalogIdentity::propertyGroupId($attribute->name, $attribute->type, $attribute->multiValue);
    }

    private function key(string $categoryGroupId, string $attributeId): string
    {
        return $categoryGroupId."\0".$attributeId;
    }
}
