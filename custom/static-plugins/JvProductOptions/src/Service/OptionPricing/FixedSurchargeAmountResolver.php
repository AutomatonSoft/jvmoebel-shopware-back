<?php declare(strict_types=1);

namespace Jv\ProductOptions\Service\OptionPricing;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;

final readonly class FixedSurchargeAmountResolver
{
    public function resolve(PriceCollection $price, string $currencyId, float $currencyFactor, bool $isGross): float
    {
        $currencyPrice = $price->getCurrencyPrice($currencyId, false);
        if (null !== $currencyPrice) {
            return $isGross ? $currencyPrice->getGross() : $currencyPrice->getNet();
        }

        $defaultPrice = $price->getCurrencyPrice(Defaults::CURRENCY, false);
        if (null !== $defaultPrice) {
            $amount = $isGross ? $defaultPrice->getGross() : $defaultPrice->getNet();

            return $amount * $currencyFactor;
        }

        return 0.0;
    }
}
