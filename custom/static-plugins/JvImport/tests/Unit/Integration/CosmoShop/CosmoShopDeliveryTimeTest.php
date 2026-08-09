<?php declare(strict_types=1);

namespace Jv\CatalogImport\Tests\Unit\Integration\CosmoShop;

use Jv\CatalogImport\Integration\CosmoShop\CosmoShopDeliveryTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CosmoShopDeliveryTimeTest extends TestCase
{
    #[DataProvider('deliveryTimes')]
    public function testItParsesTheCustomerFacingCosmoShopDeliveryTime(string $label, int $min, int $max, string $unit): void
    {
        self::assertSame(
            ['min' => $min, 'max' => $max, 'unit' => $unit],
            CosmoShopDeliveryTime::fromLabel($label),
        );
    }

    /** @return iterable<string, array{string, int, int, string}> */
    public static function deliveryTimes(): iterable
    {
        yield 'German day range' => ['Lieferzeit: 2-5 Tage', 2, 5, 'day'];
        yield 'German week range' => ['Lieferzeit: 4-8 Wochen', 4, 8, 'week'];
        yield 'English single week' => ['Delivery Time: 1 Week', 1, 1, 'week'];
    }

    public function testItDoesNotInventADeliveryTimeForAnUnavailableProduct(): void
    {
        self::assertNull(CosmoShopDeliveryTime::fromLabel('derzeit nicht lieferbar!'));
    }
}
