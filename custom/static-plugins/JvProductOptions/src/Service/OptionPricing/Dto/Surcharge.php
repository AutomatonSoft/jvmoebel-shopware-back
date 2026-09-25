<?php declare(strict_types=1);

namespace Jv\ProductOptions\Service\OptionPricing\Dto;

final readonly class Surcharge
{
    private function __construct(
        public string $type,
        public float $amount,
    ) {
    }

    public static function fixed(float $amount): self
    {
        return new self('fixed', $amount);
    }

    public static function percentage(float $percentage): self
    {
        return new self('percentage', $percentage);
    }
}
