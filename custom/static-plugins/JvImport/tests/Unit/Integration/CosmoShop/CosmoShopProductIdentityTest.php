<?php declare(strict_types=1);

namespace Jv\CatalogImport\Tests\Unit\Integration\CosmoShop;

use Jv\CatalogImport\Integration\CosmoShop\CosmoShopProductIdentity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopProductIdentityTest extends TestCase
{
    public function testItDerivesTheSharedProductIdentityFromProductNumber(): void
    {
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.product.cosmoshop.4260174423463'),
            CosmoShopProductIdentity::fromProductNumber('4260174423463'),
        );
    }

    public function testItRejectsAnEmptyProductNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CosmoShop product number must not be empty.');

        CosmoShopProductIdentity::fromProductNumber('  ');
    }
}
