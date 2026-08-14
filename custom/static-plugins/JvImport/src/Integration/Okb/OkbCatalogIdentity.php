<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb;

use Shopware\Core\Framework\Uuid\Uuid;

final class OkbCatalogIdentity
{
    public static function categoryGroupId(string $categoryGroupId): string
    {
        return self::id('category-group.'.$categoryGroupId);
    }

    public static function categoryId(string $categoryId): string
    {
        return self::id('category.'.$categoryId);
    }

    public static function categoryGroupAttributeId(string $categoryGroupId, string $attributeId): string
    {
        return self::id('category-group-attribute.'.$categoryGroupId.'.'.$attributeId);
    }

    public static function propertyGroupId(string $attributeName, string $attributeType, bool $multiValue): string
    {
        return self::id('property-group.'.self::semanticAttributeKey($attributeName, $attributeType, $multiValue));
    }

    public static function propertyOptionId(string $propertyGroupId, string $value): string
    {
        return self::id('property-option.'.$propertyGroupId.'.'.mb_strtolower(trim($value)));
    }

    public static function customFieldName(string $attributeId): string
    {
        return 'jv_okb_attribute_'.$attributeId;
    }

    private static function semanticAttributeKey(string $attributeName, string $attributeType, bool $multiValue): string
    {
        return hash('sha256', trim($attributeName)."\0".trim($attributeType)."\0".($multiValue ? '1' : '0'));
    }

    private static function id(string $value): string
    {
        return Uuid::fromStringToHex('jvmoebel.okb.'.$value);
    }
}
