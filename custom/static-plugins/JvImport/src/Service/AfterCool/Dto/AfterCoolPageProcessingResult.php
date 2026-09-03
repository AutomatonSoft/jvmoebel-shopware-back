<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Dto;

final readonly class AfterCoolPageProcessingResult
{
    private function __construct(public ?int $nextOffset)
    {
    }

    public static function continueWith(int $nextOffset): self
    {
        return new self($nextOffset);
    }

    public static function completed(): self
    {
        return new self(null);
    }
}
