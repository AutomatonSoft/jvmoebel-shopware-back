<?php declare(strict_types=1);

namespace Jv\ProductOptions\Service\OptionPricing;

use Jv\ProductOptions\Service\OptionPricing\Dto\Surcharge;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;

final readonly class OptionSurchargeCalculator
{
    public function __construct(
        private CashRounding $cashRounding,
    ) {
    }

    /**
     * @param list<Surcharge> $surcharges
     */
    public function calculate(float $baseUnitPrice, array $surcharges, CashRoundingConfig $rounding): float
    {
        $total = 0.0;

        foreach ($surcharges as $surcharge) {
            $amount = 'percentage' === $surcharge->type
                ? $baseUnitPrice * ($surcharge->amount / 100.0)
                : $surcharge->amount;

            $total += $this->cashRounding->cashRound($amount, $rounding);
        }

        return $total;
    }

    public function round(float $amount, CashRoundingConfig $rounding): float
    {
        return $this->cashRounding->cashRound($amount, $rounding);
    }
}
