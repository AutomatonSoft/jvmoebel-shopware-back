<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;

final readonly class CosmoShopCustomerRecordMapper
{
    /** @param array<array-key, string> $countryCodes */
    public function __construct(private array $countryCodes)
    {
    }

    /**
     * @param array<string, int|string|null>       $customer
     * @param list<array<string, int|string|null>> $shippingAddresses
     *
     * @return array<string, string>
     */
    public function map(Market $market, array $customer, array $shippingAddresses): array
    {
        $sourceCustomerId = (int) $customer['kd_id'];
        $shippingAddress = $this->firstShippingAddress($shippingAddresses);
        $billing = $this->address($customer, 'kd_');
        $shipping = null === $shippingAddress ? $billing : $this->address($shippingAddress, '');
        $shippingId = null === $shippingAddress
            ? CosmoShopCustomerIdentity::fallbackShippingAddressId($market, $sourceCustomerId)
            : CosmoShopCustomerIdentity::shippingAddressId($market, (int) $shippingAddress['adressen_id']);
        $vatId = trim((string) ($customer['kd_umstid'] ?? ''));
        $company = trim((string) ($customer['kd_firma'] ?? ''));
        $accountType = '' !== $company || '' !== $vatId ? 'business' : 'personal';
        $sourceAccountType = trim((string) ($customer['kd_account_type'] ?? ''));

        return [
            'id' => CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId),
            'customer_number' => $market->domain().'-'.$sourceCustomerId,
            'salutation' => $this->salutation((string) ($customer['kd_anrede'] ?? '')),
            'title' => trim((string) ($customer['kd_anrede_titel'] ?? '')),
            'first_name' => trim((string) ($customer['kd_vorname'] ?? '')),
            'last_name' => trim((string) ($customer['kd_nachname'] ?? '')),
            'email' => trim((string) ($customer['kd_mail'] ?? '')),
            'active' => $this->isActive($customer) ? '1' : '0',
            'guest' => '0',
            'birthday' => $this->birthday((string) ($customer['kd_geburtsdatum'] ?? '')),
            'vat_ids' => '' === $vatId ? '' : json_encode([$vatId], JSON_THROW_ON_ERROR),
            'custom_fields' => '' === $sourceAccountType ? '' : json_encode(['jv_cosmoshop_account_type' => $sourceAccountType], JSON_THROW_ON_ERROR),
            'billing_id' => CosmoShopCustomerIdentity::billingAddressId($market, $sourceCustomerId),
            'billing_salutation' => $billing['salutation'],
            'billing_title' => $billing['title'],
            'billing_first_name' => $billing['firstName'],
            'billing_last_name' => $billing['lastName'],
            'billing_company' => $billing['company'],
            'billing_street' => $billing['street'],
            'billing_zipcode' => $billing['zipcode'],
            'billing_city' => $billing['city'],
            'billing_country' => $billing['country'],
            'billing_phone_number' => $billing['phoneNumber'],
            'shipping_id' => $shippingId,
            'shipping_salutation' => $shipping['salutation'],
            'shipping_title' => $shipping['title'],
            'shipping_first_name' => $shipping['firstName'],
            'shipping_last_name' => $shipping['lastName'],
            'shipping_company' => $shipping['company'],
            'shipping_street' => $shipping['street'],
            'shipping_zipcode' => $shipping['zipcode'],
            'shipping_city' => $shipping['city'],
            'shipping_country' => $shipping['country'],
            'shipping_phone_number' => $shipping['phoneNumber'],
            'account_type' => $accountType,
        ];
    }

    /**
     * @param list<array<string, int|string|null>> $addresses
     *
     * @return array<string, int|string|null>|null
     */
    private function firstShippingAddress(array $addresses): ?array
    {
        $addresses = array_filter($addresses, static fn (array $address): bool => ($address['typ'] ?? null) === 'lief');
        usort($addresses, static fn (array $left, array $right): int => (int) $left['adressen_id'] <=> (int) $right['adressen_id']);

        return $addresses[0] ?? null;
    }

    /**
     * @param array<string, int|string|null> $address
     *
     * @return array{salutation: string, title: string, firstName: string, lastName: string, company: string, street: string, zipcode: string, city: string, country: string, phoneNumber: string}
     */
    private function address(array $address, string $prefix): array
    {
        $field = static fn (string $name): string => trim((string) ($address[$prefix.$name] ?? ''));
        $street = trim(implode(' ', array_filter([$field('strasse'), $field('hausnr')], static fn (string $value): bool => '' !== $value)));
        $countryId = $address[$prefix.'land'] ?? $address['land_id'] ?? null;

        return [
            'salutation' => $this->salutation((string) ($address[$prefix.'anrede'] ?? $address['geschlecht'] ?? '')),
            'title' => $field('anrede_titel'),
            'firstName' => $field('vorname'),
            'lastName' => $field('nachname'),
            'company' => $field('firma'),
            'street' => $street,
            'zipcode' => $field('plz'),
            'city' => $field('ort'),
            'country' => $this->countryCode($countryId),
            'phoneNumber' => $field('tel'),
        ];
    }

    private function countryCode(int|string|null $countryId): string
    {
        return $this->countryCodes[(string) $countryId] ?? '';
    }

    /** @param array<string, int|string|null> $customer */
    private function isActive(array $customer): bool
    {
        return ($customer['kd_status'] ?? null) === 'k' && 0 === (int) ($customer['kd_is_locked'] ?? 0);
    }

    private function salutation(string $sourceSalutation): string
    {
        return match (mb_strtolower(trim($sourceSalutation))) {
            'm' => 'mr',
            'w' => 'mrs',
            default => 'not_specified',
        };
    }

    private function birthday(string $value): string
    {
        $birthday = \DateTimeImmutable::createFromFormat('!d.m.Y', trim($value));
        $errors = \DateTimeImmutable::getLastErrors();
        if (false === $birthday || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return '';
        }

        return $birthday->format('Y-m-d');
    }
}
