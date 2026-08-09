<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop;

use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopProductIdentity
{
    public static function fromProductNumber(string $productNumber): string
    {
        $productNumber = trim($productNumber);
        if ('' === $productNumber) {
            throw new \InvalidArgumentException('CosmoShop product number must not be empty.');
        }

        return Uuid::fromStringToHex('jvmoebel.product.cosmoshop.'.$productNumber);
    }
}
