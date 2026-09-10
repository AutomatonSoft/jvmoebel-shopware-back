<?php declare(strict_types=1);

namespace Jv\Import\Service\CustomerImport\Dto;

final readonly class ApplyCosmoShopCustomerWishlistsResult
{
    /** @param list<class-string<\Throwable>> $exceptionClasses */
    public function __construct(
        public int $processed,
        public int $ready,
        public int $wishlists,
        public int $written,
        public int $existing,
        public int $duplicate,
        public int $guest,
        public int $sourceOrphanProduct,
        public int $missingCustomer,
        public int $missingProduct,
        public int $failed,
        private array $exceptionClasses = [],
    ) {
    }

    public function hasFailures(): bool
    {
        return 0 !== $this->missingCustomer || 0 !== $this->missingProduct || 0 !== $this->failed;
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return ['processed' => $this->processed, 'ready' => $this->ready, 'wishlists' => $this->wishlists, 'written' => $this->written, 'existing' => $this->existing, 'duplicate' => $this->duplicate, 'guest' => $this->guest, 'source_orphan_product' => $this->sourceOrphanProduct, 'missing_customer' => $this->missingCustomer, 'missing_product' => $this->missingProduct, 'failed' => $this->failed];
    }

    /** @return list<class-string<\Throwable>> */
    public function exceptionClasses(): array
    {
        return array_values(array_unique($this->exceptionClasses));
    }
}
