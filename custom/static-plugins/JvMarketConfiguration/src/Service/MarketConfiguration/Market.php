<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Service\MarketConfiguration;

use Shopware\Core\Framework\Uuid\Uuid;

enum Market: string
{
    case Germany = 'jvmoebel.de';
    case Austria = 'jvmoebel.at';
    case Switzerland = 'jvmoebel.ch';
    case UnitedKingdom = 'jvfurniture.co.uk';
    case Italy = 'jvmobili.it';
    case Poland = 'jvmeble.pl';

    public function domain(): string
    {
        return $this->value;
    }

    public function displayName(): string
    {
        return $this->translatedNames()['de-DE'];
    }

    /**
     * @return array<string, string>
     */
    public function translatedNames(): array
    {
        return match ($this) {
            self::Germany => ['de-DE' => 'JVMöbel Deutschland', 'en-GB' => 'JVMöbel Germany'],
            self::Austria => ['de-DE' => 'JVMöbel Österreich', 'en-GB' => 'JVMöbel Austria'],
            self::Switzerland => ['de-DE' => 'JVMöbel Schweiz', 'en-GB' => 'JVMöbel Switzerland'],
            self::UnitedKingdom => ['de-DE' => 'JV Furniture', 'en-GB' => 'JV Furniture'],
            self::Italy => ['de-DE' => 'JVMöbel Italia', 'en-GB' => 'JVMöbel Italy'],
            self::Poland => ['de-DE' => 'JVMöbel Polska', 'en-GB' => 'JVMöbel Poland'],
        };
    }

    public function languageCode(): string
    {
        return match ($this) {
            self::UnitedKingdom => 'en-GB',
            self::Germany, self::Austria, self::Switzerland, self::Italy, self::Poland => 'de-DE',
        };
    }

    public function currencyCode(): string
    {
        return match ($this) {
            self::Switzerland => 'CHF',
            self::UnitedKingdom => 'GBP',
            self::Germany, self::Austria, self::Italy, self::Poland => 'EUR',
        };
    }

    public function countryCode(): string
    {
        return match ($this) {
            self::Germany => 'DE',
            self::Austria => 'AT',
            self::Switzerland => 'CH',
            self::UnitedKingdom => 'GB',
            self::Italy => 'IT',
            self::Poland => 'PL',
        };
    }

    public function salesChannelId(): string
    {
        return Uuid::fromStringToHex('jvmoebel.sales-channel.'.$this->domain());
    }

    public function languageId(): string
    {
        return Uuid::fromStringToHex('jvmoebel.language.market.'.$this->domain());
    }

    public function salesChannelDomainId(): string
    {
        return Uuid::fromStringToHex('jvmoebel.sales-channel-domain.'.$this->domain());
    }

    public function url(string $urlTemplate = 'https://{domain}'): string
    {
        return str_replace('{domain}', $this->domain(), $urlTemplate);
    }
}
