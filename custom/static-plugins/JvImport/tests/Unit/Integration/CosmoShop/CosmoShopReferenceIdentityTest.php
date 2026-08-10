<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop;

use Jv\Import\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class CosmoShopReferenceIdentityTest extends TestCase
{
    public function testItBuildsStableShopwareIdsForCosmoShopReferenceRecords(): void
    {
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.delivery-time.cosmoshop.jvmoebel.de.2'),
            CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, '2'),
        );
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.unit.cosmoshop.jvmoebel.de.6'),
            CosmoShopReferenceIdentity::unitId(Market::Germany, '6'),
        );
    }

    public function testItRejectsMissingCosmoShopReferenceIds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CosmoShop delivery time ID must be a non-negative integer.');

        CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, '');
    }

    public function testItSeparatesEqualCosmoShopIdsFromDifferentMarkets(): void
    {
        self::assertNotSame(
            CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, '2'),
            CosmoShopReferenceIdentity::deliveryTimeId(Market::UnitedKingdom, '2'),
        );
    }
}
