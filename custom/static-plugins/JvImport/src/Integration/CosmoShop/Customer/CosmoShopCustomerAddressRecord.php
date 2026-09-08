<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

final readonly class CosmoShopCustomerAddressRecord
{
    public function __construct(
        public ?int $sourceCustomerId,
        public ?int $sourceAddressId,
        public string $salutation,
        public string $title,
        public string $firstName,
        public string $lastName,
        public string $company,
        public string $street,
        public string $zipcode,
        public string $city,
        public string $country,
        public string $phoneNumber,
        public bool $isWellFormed,
    ) {
    }
}
