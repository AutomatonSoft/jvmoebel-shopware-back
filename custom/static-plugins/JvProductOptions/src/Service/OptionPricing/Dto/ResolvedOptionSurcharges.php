<?php declare(strict_types=1);

namespace Jv\ProductOptions\Service\OptionPricing\Dto;

final readonly class ResolvedOptionSurcharges
{
    /**
     * @param list<ResolvedValueSurcharge> $values
     */
    public function __construct(
        public array $values,
        public float $totalUnitAmount,
    ) {
    }
}
