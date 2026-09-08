<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

final readonly class CosmoShopCustomerPasswordRecord
{
    public function __construct(
        public ?int $sourceCustomerId,
        public string $password,
        public string $salt,
        public bool $isWellFormed,
    ) {
    }
}
