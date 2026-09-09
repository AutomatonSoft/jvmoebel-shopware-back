<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport;

use Shopware\Core\Framework\Uuid\Uuid;

final class ProductManufacturerIdentity
{
    public static function jvmoebel(): string
    {
        return Uuid::fromStringToHex('jvmoebel.product-manufacturer.cosmoshop.jvmoebel');
    }
}
