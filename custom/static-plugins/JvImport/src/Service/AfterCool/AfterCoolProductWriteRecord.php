<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

final readonly class AfterCoolProductWriteRecord
{
    /** @param array<string, mixed> $payload */
    public function __construct(public string $sourceProductId, public array $payload)
    {
    }
}
