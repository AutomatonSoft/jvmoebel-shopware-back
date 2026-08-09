<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopReferenceIdentity
{
    public static function deliveryTimeId(Market $market, mixed $sourceId): string
    {
        return self::id($market, $sourceId, 'delivery time');
    }

    public static function unitId(Market $market, mixed $sourceId): string
    {
        return self::id($market, $sourceId, 'unit');
    }

    private static function id(Market $market, mixed $sourceId, string $type): string
    {
        $sourceId = trim((string) $sourceId);
        if (!ctype_digit($sourceId)) {
            throw new \InvalidArgumentException(sprintf('CosmoShop %s ID must be a non-negative integer.', $type));
        }

        return Uuid::fromStringToHex(sprintf('jvmoebel.%s.cosmoshop.%s.%s', str_replace(' ', '-', $type), $market->domain(), $sourceId));
    }
}
