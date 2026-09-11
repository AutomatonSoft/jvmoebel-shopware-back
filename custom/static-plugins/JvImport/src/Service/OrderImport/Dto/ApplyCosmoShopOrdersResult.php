<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

final readonly class ApplyCosmoShopOrdersResult
{
    /** @param array<string, int> $counts */
    public function __construct(private array $counts)
    {
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return $this->counts;
    }

    public function hasFailures(): bool
    {
        return 0 !== ($this->counts['invalid'] ?? 0)
            || 0 !== ($this->counts['collision'] ?? 0)
            || 0 !== ($this->counts['incomplete_address'] ?? 0)
            || 0 !== ($this->counts['failed'] ?? 0);
    }
}
