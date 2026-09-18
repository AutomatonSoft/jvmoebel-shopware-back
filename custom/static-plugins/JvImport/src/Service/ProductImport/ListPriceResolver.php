<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport;

final class ListPriceResolver
{
    public function resolve(float $price, ?float $suggestedRetailPrice, ?float $sourceListPrice): float
    {
        if (null !== $suggestedRetailPrice && $suggestedRetailPrice > $price) {
            return $suggestedRetailPrice;
        }
        if (null !== $sourceListPrice && $sourceListPrice > $price) {
            return $sourceListPrice;
        }

        return $this->calculate($price);
    }

    private function calculate(float $price): float
    {
        $factor = $price > 5000 ? 1.10 : ($price >= 2500 && $price <= 4999 ? 1.18 : ($price >= 1000 && $price <= 2499 ? 1.25 : 1.35));

        return round($price * $factor, 2);
    }
}
