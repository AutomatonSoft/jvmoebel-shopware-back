<?php declare(strict_types=1);

namespace Jv\CatalogImport\Integration\CosmoShop;

use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopReferenceIdentity
{
    public static function deliveryTimeId(mixed $sourceId): string
    {
        return self::id($sourceId, 'delivery time');
    }

    public static function unitId(mixed $sourceId): string
    {
        return self::id($sourceId, 'unit');
    }

    private static function id(mixed $sourceId, string $type): string
    {
        $sourceId = trim((string) $sourceId);
        if (!ctype_digit($sourceId)) {
            throw new \InvalidArgumentException(sprintf('CosmoShop %s ID must be a non-negative integer.', $type));
        }

        return Uuid::fromStringToHex(sprintf('jvmoebel.%s.cosmoshop.%s', str_replace(' ', '-', $type), $sourceId));
    }
}
