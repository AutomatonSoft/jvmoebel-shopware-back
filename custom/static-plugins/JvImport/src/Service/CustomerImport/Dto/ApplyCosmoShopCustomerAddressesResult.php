<?php declare(strict_types=1);

namespace Jv\Import\Service\CustomerImport\Dto;

final readonly class ApplyCosmoShopCustomerAddressesResult
{
    /** @param list<class-string<\Throwable>> $exceptionClasses */
    public function __construct(
        public int $processed,
        public int $ready,
        public int $written,
        public int $missingCustomer,
        public int $failed,
        private array $exceptionClasses = [],
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
            'ready' => $this->ready,
            'written' => $this->written,
            'missing_customer' => $this->missingCustomer,
            'failed' => $this->failed,
        ];
    }

    /** @return list<class-string<\Throwable>> */
    public function exceptionClasses(): array
    {
        return array_values(array_unique($this->exceptionClasses));
    }
}
