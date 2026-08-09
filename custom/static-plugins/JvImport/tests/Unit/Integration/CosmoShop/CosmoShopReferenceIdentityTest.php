<?php declare(strict_types=1);

namespace Jv\CatalogImport\Tests\Unit\Integration\CosmoShop;

use Jv\CatalogImport\Integration\CosmoShop\CosmoShopReferenceIdentity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopReferenceIdentityTest extends TestCase
{
    public function testItBuildsStableShopwareIdsForCosmoShopReferenceRecords(): void
    {
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.delivery-time.cosmoshop.2'),
            CosmoShopReferenceIdentity::deliveryTimeId('2'),
        );
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.unit.cosmoshop.6'),
            CosmoShopReferenceIdentity::unitId('6'),
        );
    }

    public function testItRejectsMissingCosmoShopReferenceIds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CosmoShop delivery time ID must be a non-negative integer.');

        CosmoShopReferenceIdentity::deliveryTimeId('');
    }
}
