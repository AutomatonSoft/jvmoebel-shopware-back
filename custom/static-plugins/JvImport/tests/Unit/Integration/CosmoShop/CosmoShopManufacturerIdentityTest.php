<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop;

use Jv\Import\Integration\CosmoShop\CosmoShopManufacturerIdentity;
use PHPUnit\Framework\TestCase;

final class CosmoShopManufacturerIdentityTest extends TestCase
{
    public function testItUsesTheSameIdentityForEquivalentManufacturerNames(): void
    {
        self::assertSame(
            CosmoShopManufacturerIdentity::fromName('JVMöbel GmbH'),
            CosmoShopManufacturerIdentity::fromName('  jvmöbel   gmbh '),
        );
    }

    public function testItRejectsAnEmptyManufacturerName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CosmoShopManufacturerIdentity::fromName('  ');
    }
}
