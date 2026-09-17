<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport;

use Jv\Import\Service\ProductImport\ListPriceResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListPriceResolverTest extends TestCase
{
    public function testTheSuggestedRetailPriceWins(): void
    {
        self::assertSame(8389.0, (new ListPriceResolver())->resolve(7229.0, 8389.0, 9000.0));
    }

    public function testTheSourceListPriceIsUsedWithoutASuggestedRetailPrice(): void
    {
        self::assertSame(9000.0, (new ListPriceResolver())->resolve(7229.0, null, 9000.0));
    }

    public function testASuggestedRetailPriceBelowThePriceIsIgnored(): void
    {
        self::assertSame(9000.0, (new ListPriceResolver())->resolve(7229.0, 7000.0, 9000.0));
    }

    public function testASourceListPriceBelowThePriceIsCalculatedInstead(): void
    {
        self::assertSame(1250.0, (new ListPriceResolver())->resolve(1000.0, null, 900.0));
    }

    public function testAMissingListPriceIsCalculatedFromThePrice(): void
    {
        self::assertSame(135.0, (new ListPriceResolver())->resolve(100.0, null, null));
    }

    #[DataProvider('bandProvider')]
    public function testTheCalculatedBandsMatchTheAfterCoolRule(float $price, float $expected): void
    {
        self::assertSame($expected, (new ListPriceResolver())->resolve($price, null, null));
    }

    /** @return iterable<string, array{float, float}> */
    public static function bandProvider(): iterable
    {
        yield 'below a thousand' => [999.0, 1348.65];
        yield 'lower band starts' => [1000.0, 1250.0];
        yield 'lower band ends' => [2499.0, 3123.75];
        yield 'middle band starts' => [2500.0, 2950.0];
        yield 'middle band ends' => [4999.0, 5898.82];
        yield 'above the middle band the rule falls back' => [5000.0, 6750.0];
        yield 'top band starts above five thousand' => [5001.0, 5501.1];
    }
}
