<?php declare(strict_types=1);

namespace Jv\ProductOptions\Tests\Unit\Service\OptionPricing;

use Jv\ProductOptions\Service\OptionPricing\Dto\Surcharge;
use Jv\ProductOptions\Service\OptionPricing\OptionSurchargeCalculator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;

final class OptionSurchargeCalculatorTest extends TestCase
{
    private OptionSurchargeCalculator $calculator;

    private CashRoundingConfig $rounding;

    protected function setUp(): void
    {
        $this->calculator = new OptionSurchargeCalculator(new CashRounding());
        $this->rounding = new CashRoundingConfig(2, 0.01, true);
    }

    public function testNoSurchargesAddNothing(): void
    {
        self::assertSame(0.0, $this->calculator->calculate(1000.0, [], $this->rounding));
    }

    public function testFixedSurchargeAddsItsAmount(): void
    {
        self::assertSame(200.0, $this->calculator->calculate(1000.0, [Surcharge::fixed(200.0)], $this->rounding));
    }

    public function testPercentageSurchargeIsTakenFromBasePrice(): void
    {
        self::assertSame(200.0, $this->calculator->calculate(1000.0, [Surcharge::percentage(20.0)], $this->rounding));
    }

    public function testPercentageIgnoresOtherSurcharges(): void
    {
        $surcharges = [Surcharge::fixed(500.0), Surcharge::percentage(10.0), Surcharge::percentage(10.0)];

        self::assertSame(700.0, $this->calculator->calculate(1000.0, $surcharges, $this->rounding));
    }

    public function testEachSurchargeIsRoundedBeforeSumming(): void
    {
        $surcharges = [Surcharge::percentage(0.5), Surcharge::percentage(0.5)];

        self::assertSame(0.06, $this->calculator->calculate(5.3, $surcharges, $this->rounding));
    }

    public function testZeroSurchargesAreAllowed(): void
    {
        self::assertSame(0.0, $this->calculator->calculate(999.99, [Surcharge::fixed(0.0), Surcharge::percentage(0.0)], $this->rounding));
    }
}
