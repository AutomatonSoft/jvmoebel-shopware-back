<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Service\MarketConfiguration;

use Jv\MarketConfiguration\Service\MarketConfiguration\Dto\MarketDefinition;

final class MarketDefinitions
{
    public function __construct(
        private readonly string $salesChannelUrlTemplate = 'https://{domain}',
    ) {
    }

    /**
     * @return list<MarketDefinition>
     */
    public function all(): array
    {
        return [
            $this->market(
                'jvmoebel.de',
                'JVMöbel Deutschland',
                ['de-DE' => 'JVMöbel Deutschland', 'en-GB' => 'JVMöbel Germany'],
                'de-DE',
                'EUR',
                'DE',
            ),
            $this->market(
                'jvmoebel.at',
                'JVMöbel Österreich',
                ['de-DE' => 'JVMöbel Österreich', 'en-GB' => 'JVMöbel Austria'],
                'de-DE',
                'EUR',
                'AT',
            ),
            $this->market(
                'jvmoebel.ch',
                'JVMöbel Schweiz',
                ['de-DE' => 'JVMöbel Schweiz', 'en-GB' => 'JVMöbel Switzerland'],
                'de-DE',
                'CHF',
                'CH',
            ),
            $this->market(
                'jvfurniture.co.uk',
                'JV Furniture',
                ['de-DE' => 'JV Furniture', 'en-GB' => 'JV Furniture'],
                'en-GB',
                'GBP',
                'GB',
            ),
            $this->market(
                'jvmobili.it',
                'JVMöbel Italia',
                ['de-DE' => 'JVMöbel Italia', 'en-GB' => 'JVMöbel Italy'],
                'de-DE',
                'EUR',
                'IT',
            ),
            $this->market(
                'jvmeble.pl',
                'JVMöbel Polska',
                ['de-DE' => 'JVMöbel Polska', 'en-GB' => 'JVMöbel Poland'],
                'de-DE',
                'EUR',
                'PL',
            ),
        ];
    }

    /**
     * @param array<string, string> $translatedNames
     */
    private function market(
        string $domain,
        string $name,
        array $translatedNames,
        string $languageCode,
        string $currencyCode,
        string $countryCode,
    ): MarketDefinition {
        return new MarketDefinition(
            $domain,
            $name,
            $translatedNames,
            $languageCode,
            $currencyCode,
            $countryCode,
            $this->salesChannelUrlTemplate,
        );
    }
}
