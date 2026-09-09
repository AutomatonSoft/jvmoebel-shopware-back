<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport;

use Shopware\Core\Framework\Uuid\Uuid;

final class ProductImportIdentity
{
    public static function fromProductNumber(string $productNumber): string
    {
        $productNumber = trim($productNumber);
        if ('' === $productNumber) {
            throw new \InvalidArgumentException('Product number must not be empty.');
        }

        return Uuid::fromStringToHex('jvmoebel.product.'.$productNumber);
    }
}
