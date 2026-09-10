<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop;

use Jv\Import\Service\ProductImport\ProductManufacturerIdentity;

final class CosmoShopManufacturerIdentity
{
    public static function fromName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ('' === $name) {
            throw new \InvalidArgumentException('CosmoShop manufacturer name must not be empty.');
        }

        if ('jvmoebel' === mb_strtolower($name)) {
            return ProductManufacturerIdentity::jvmoebel();
        }

        return \Shopware\Core\Framework\Uuid\Uuid::fromStringToHex('jvmoebel.product-manufacturer.cosmoshop.'.mb_strtolower($name));
    }
}
