<?php declare(strict_types=1);

namespace Jv\Import\Service\CustomerImport\Dto;

final readonly class ApplyCosmoShopCustomerPasswordsResult
{
    public function __construct(
        public int $processed,
        public int $legacy,
        public int $rehash,
        public int $resetRequired,
        public int $protectedCurrent,
        public int $missingCustomer,
        public int $failed,
    ) {
    }

    public function hasFailures(): bool
    {
        return 0 !== $this->missingCustomer || 0 !== $this->failed;
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return [
            'processed' => $this->processed,
            'legacy' => $this->legacy,
            'rehash' => $this->rehash,
            'reset_required' => $this->resetRequired,
            'protected_current' => $this->protectedCurrent,
            'missing_customer' => $this->missingCustomer,
            'failed' => $this->failed,
        ];
    }
}
