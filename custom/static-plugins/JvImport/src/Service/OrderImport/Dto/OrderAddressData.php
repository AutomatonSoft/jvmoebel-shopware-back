<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

/** Normalized, validated source address snapshot (billing, shipping or packing). */
final readonly class OrderAddressData
{
    public function __construct(
        public string $sourceType,
        public ?int $sourceAddressId,
        public OrderSalutation $salutation,
        public string $title,
        public string $firstName,
        public string $lastName,
        public string $company,
        public string $street,
        public string $zipcode,
        public string $city,
        public string $country,
        public string $state,
        public string $email,
        public string $phone,
        public string $vatId,
    ) {
    }
}
