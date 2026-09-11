<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

/**
 * Builds Shopware calculated-price structures from validated source monetary values.
 * Every value entering a Shopware price structure is rounded half-up to the cent here; the
 * unrounded source strings are preserved separately in the line-item payload by the caller.
 */
final class CosmoShopOrderPriceProjector
{
    private const int DECIMALS = 2;

    /** @return array<string, mixed> */
    public function item(float $unit, float $total, int $quantity, float $taxRate, float $tax): array
    {
        $roundedTotal = $this->round($total);

        return [
            'unitPrice' => $this->round($unit),
            'totalPrice' => $roundedTotal,
            'quantity' => $quantity,
            'calculatedTaxes' => [['taxRate' => $taxRate, 'price' => $roundedTotal, 'tax' => $this->round($tax)]],
            'taxRules' => [['taxRate' => $taxRate, 'percentage' => 100.0]],
        ];
    }

    /**
     * @param array<string, array{taxRate: float, price: float, tax: float}> $taxes
     *
     * @return array<string, mixed>
     */
    public function aggregate(float $total, float $net, float $tax, array $taxes): array
    {
        $roundedTotal = $this->round($total);
        $calculatedTaxes = array_values(array_map(
            fn (array $entry): array => ['taxRate' => $entry['taxRate'], 'price' => $this->round($entry['price'] + $entry['tax']), 'tax' => $this->round($entry['tax'])],
            $taxes,
        ));

        return [
            'unitPrice' => $roundedTotal,
            'totalPrice' => $roundedTotal,
            'quantity' => 1,
            'calculatedTaxes' => $calculatedTaxes,
            'taxRules' => $this->rules($taxes, $total),
        ];
    }

    /**
     * @param array<string, array{taxRate: float, price: float, tax: float}> $taxes
     *
     * @return array<string, mixed>
     */
    public function cart(float $total, float $net, float $positionPrice, string $taxStatus, array $taxes): array
    {
        $calculatedTaxes = array_values(array_map(function (array $entry) use ($taxStatus): array {
            if ('tax-free' === $taxStatus) {
                return ['taxRate' => $entry['taxRate'], 'price' => 0.0, 'tax' => 0.0];
            }
            $price = 'gross' === $taxStatus ? $entry['price'] + $entry['tax'] : $entry['price'];

            return ['taxRate' => $entry['taxRate'], 'price' => $this->round($price), 'tax' => $this->round($entry['tax'])];
        }, $taxes));

        return [
            'netPrice' => $this->round($net),
            'totalPrice' => $this->round($total),
            'positionPrice' => $this->round($positionPrice),
            'rawTotal' => $this->round($total),
            'taxStatus' => $taxStatus,
            'calculatedTaxes' => $calculatedTaxes,
            'taxRules' => $this->rules($taxes, 'net' === $taxStatus ? $net : $total),
        ];
    }

    /** @param array<string, array{taxRate: float, price: float, tax: float}> $taxes */
    public function add(array &$taxes, float $rate, float $net, float $tax): void
    {
        $key = 'rate-'.$rate;
        $taxes[$key] ??= ['taxRate' => $rate, 'price' => 0.0, 'tax' => 0.0];
        $taxes[$key]['price'] += $net;
        $taxes[$key]['tax'] += $tax;
    }

    /** @return array{decimals: int, interval: float, roundForNet: bool} */
    public function rounding(): array
    {
        return ['decimals' => self::DECIMALS, 'interval' => 0.01, 'roundForNet' => false];
    }

    public function round(float $value): float
    {
        return round($value, self::DECIMALS, \PHP_ROUND_HALF_UP);
    }

    /**
     * @param array<string, array{taxRate: float, price: float, tax: float}> $taxes
     *
     * @return list<array{taxRate: float, percentage: float}>
     */
    private function rules(array $taxes, float $basis): array
    {
        if (0.0 === $basis) {
            return [];
        }

        return array_values(array_map(static fn (array $entry): array => ['taxRate' => $entry['taxRate'], 'percentage' => 100.0 * $entry['price'] / $basis], $taxes));
    }
}
