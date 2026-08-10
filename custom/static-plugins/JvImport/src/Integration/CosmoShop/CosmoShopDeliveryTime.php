<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop;

final class CosmoShopDeliveryTime
{
    /** @return array{min: int, max: int, unit: 'day'|'week'} */
    public static function fromLabel(string $label): array
    {
        $label = mb_strtolower(trim($label));
        if (str_contains($label, 'nicht lieferbar') || str_contains($label, 'not on stock')) {
            throw new \InvalidArgumentException(sprintf('CosmoShop delivery time label "%s" marks the product as unavailable.', $label));
        }

        if (1 !== preg_match('/(?<min>\d+)\s*(?:-|–)?\s*(?<max>\d+)?\s*(?<unit>tage|tag|wochen|woche|days|day|weeks|week)/u', $label, $matches)) {
            throw new \InvalidArgumentException(sprintf('CosmoShop delivery time label "%s" cannot be parsed.', $label));
        }

        $min = (int) $matches['min'];
        $max = '' === $matches['max'] ? $min : (int) $matches['max'];

        return [
            'min' => $min,
            'max' => $max,
            'unit' => match ($matches['unit']) {
                'tage', 'tag', 'days', 'day' => 'day',
                'wochen', 'woche', 'weeks', 'week' => 'week',
            },
        ];
    }
}
