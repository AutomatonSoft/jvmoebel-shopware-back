<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;

final readonly class CosmoShopCustomerAddressRecordMapper
{
    /** @var array<string, string> */
    private array $countries;

    /** @param array<int|string, string> $countries */
    public function __construct(array $countries)
    {
        $normalizedCountries = [];
        foreach ($countries as $sourceId => $countryCode) {
            $normalizedCountries[trim((string) $sourceId)] = mb_strtoupper(trim($countryCode));
        }

        $this->countries = $normalizedCountries;
    }

    /** @param array<string, int|string|null> $source
     * @return array<string, string>
     */
    public function map(Market $market, array $source): array
    {
        $field = static fn (string $name): string => trim((string) ($source[$name] ?? ''));
        $street = trim(implode(' ', array_filter(
            [$field('strasse'), $field('hausnr')],
            static fn (string $value): bool => '' !== $value,
        )));
        $salutation = match (mb_strtolower($field('geschlecht'))) {
            'm' => 'mr',
            'w' => 'mrs',
            default => 'not_specified',
        };

        return [
            'source_customer_id' => $field('kunden_id'),
            'source_address_id' => $field('adressen_id'),
            'salutation' => $salutation,
            'title' => '',
            'first_name' => $field('vorname'),
            'last_name' => $field('nachname'),
            'company' => $field('firma'),
            'street' => $street,
            'zipcode' => $field('plz'),
            'city' => $field('ort'),
            'country' => $this->countries[$field('land_id')] ?? '',
            'phone_number' => $field('tel'),
        ];
    }
}
