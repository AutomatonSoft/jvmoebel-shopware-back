<?php declare(strict_types=1);

namespace Jv\ProductOptions\Service\OptionPricing\Dto;

final readonly class ResolvedValueSurcharge
{
    public function __construct(
        public string $valueId,
        public string $groupId,
        public string $type,
        public ?float $percentage,
        public float $unitAmount,
    ) {
    }
}
