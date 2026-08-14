<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog;

use Shopware\Core\Framework\Uuid\Uuid;

final class CatalogIdentity
{
    public static function categoryGroupId(string $sourceCode, string $categoryGroupKey): string
    {
        return self::id($sourceCode.'.category-group.'.$categoryGroupKey);
    }

    public static function categoryId(string $sourceCode, string $categoryKey): string
    {
        return self::id($sourceCode.'.category.'.$categoryKey);
    }

    public static function categoryAttributeId(string $sourceCode, string $categoryGroupKey, string $attributeKey): string
    {
        return self::id($sourceCode.'.category-attribute.'.$categoryGroupKey.'.'.$attributeKey);
    }

    public static function propertyGroupId(string $attributeName, string $attributeType, bool $multiValue): string
    {
        return self::id('property-group.'.hash('sha256', trim($attributeName)."\0".trim($attributeType)."\0".($multiValue ? '1' : '0')));
    }

    public static function propertyOptionId(string $propertyGroupId, string $value): string
    {
        return self::id('property-option.'.$propertyGroupId.'.'.mb_strtolower(trim($value)));
    }

    private static function id(string $value): string
    {
        return Uuid::fromStringToHex('jvmoebel.'.$value);
    }
}
