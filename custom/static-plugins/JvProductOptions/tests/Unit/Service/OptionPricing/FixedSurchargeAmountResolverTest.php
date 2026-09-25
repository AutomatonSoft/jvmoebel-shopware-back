<?php declare(strict_types=1);

namespace Jv\ProductOptions\Tests\Unit\Service\OptionPricing;

use Jv\ProductOptions\Service\OptionPricing\FixedSurchargeAmountResolver;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Uuid\Uuid;

final class FixedSurchargeAmountResolverTest extends TestCase
{
    private FixedSurchargeAmountResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new FixedSurchargeAmountResolver();
    }

    public function testDefaultCurrencyGrossAmount(): void
    {
        $price = new PriceCollection([new Price(Defaults::CURRENCY, 168.07, 200.0, false)]);

        self::assertSame(200.0, $this->resolver->resolve($price, Defaults::CURRENCY, 1.0, true));
    }

    public function testNetAmountForNetTaxState(): void
    {
        $price = new PriceCollection([new Price(Defaults::CURRENCY, 168.07, 200.0, false)]);

        self::assertSame(168.07, $this->resolver->resolve($price, Defaults::CURRENCY, 1.0, false));
    }

    public function testOtherCurrencyIsConvertedByFactor(): void
    {
        $price = new PriceCollection([new Price(Defaults::CURRENCY, 168.07, 200.0, false)]);

        self::assertEqualsWithDelta(1000.0, $this->resolver->resolve($price, Uuid::randomHex(), 5.0, true), 0.0001);
    }

    public function testExplicitCurrencyPriceWinsOverConversion(): void
    {
        $currencyId = Uuid::randomHex();
        $price = new PriceCollection([
            new Price(Defaults::CURRENCY, 168.07, 200.0, false),
            new Price($currencyId, 800.0, 950.0, false),
        ]);

        self::assertSame(950.0, $this->resolver->resolve($price, $currencyId, 5.0, true));
    }
}
