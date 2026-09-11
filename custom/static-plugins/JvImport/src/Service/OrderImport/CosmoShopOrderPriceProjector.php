<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

/** Builds Shopware calculated-price structures from validated source monetary values. */
final class CosmoShopOrderPriceProjector
{
    /** @return array<string, mixed> */
    public function item(float $unit, float $total, int $quantity, float $taxRate, float $tax): array
    {
        return ['unitPrice' => $unit, 'totalPrice' => $total, 'quantity' => $quantity, 'calculatedTaxes' => [['taxRate' => $taxRate, 'price' => $total, 'tax' => $tax]], 'taxRules' => [['taxRate' => $taxRate, 'percentage' => 100.0]]];
    }

    /** @param array<string, array{taxRate: float, price: float, tax: float}> $taxes
     * @return array<string, mixed>
     */
    public function aggregate(float $total, float $net, float $tax, array $taxes): array
    {
        $taxes = array_map(static fn (array $entry): array => [...$entry, 'price' => $entry['price'] + $entry['tax']], $taxes);

        return ['unitPrice' => $total, 'totalPrice' => $total, 'quantity' => 1, 'calculatedTaxes' => array_values($taxes), 'taxRules' => $this->rules($taxes, $total)];
    }

    /** @param array<string, array{taxRate: float, price: float, tax: float}> $taxes
     * @return array<string, mixed>
     */
    public function cart(float $total, float $net, float $positionPrice, string $taxStatus, array $taxes): array
    {
        $taxes = 'tax-free' === $taxStatus ? array_map(static fn (array $entry): array => [...$entry, 'price' => 0.0, 'tax' => 0.0], $taxes) : array_map(static fn (array $entry): array => [...$entry, 'price' => 'gross' === $taxStatus ? $entry['price'] + $entry['tax'] : $entry['price']], $taxes);

        return ['netPrice' => $net, 'totalPrice' => $total, 'positionPrice' => $positionPrice, 'rawTotal' => $total, 'taxStatus' => $taxStatus, 'calculatedTaxes' => array_values($taxes), 'taxRules' => $this->rules($taxes, 'net' === $taxStatus ? $net : $total)];
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
        return ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => false];
    }

    /** @param array<string, array{taxRate: float, price: float, tax: float}> $taxes
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
