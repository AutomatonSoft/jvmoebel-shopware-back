<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Service\MarketConfiguration\Dto;

use Shopware\Core\Framework\Uuid\Uuid;

final readonly class MarketDefinition
{
    /**
     * @param array<string, string> $translatedNames language code => display name
     */
    public function __construct(
        public string $domain,
        public string $name,
        public array $translatedNames,
        public string $languageCode,
        public string $currencyCode,
        public string $countryCode,
        private string $urlTemplate = 'https://{domain}',
    ) {
    }

    public function salesChannelId(): string
    {
        return Uuid::fromStringToHex('jvmoebel.sales-channel.'.$this->domain);
    }

    public function salesChannelDomainId(): string
    {
        return Uuid::fromStringToHex('jvmoebel.sales-channel-domain.'.$this->domain);
    }

    public function url(): string
    {
        return str_replace('{domain}', $this->domain, $this->urlTemplate);
    }
}
