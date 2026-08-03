<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Service\MarketConfiguration;

use Jv\MarketConfiguration\Service\MarketConfiguration\Dto\MarketDefinition;

final class MarketDefinitions
{
    /**
     * @return list<MarketDefinition>
     */
    public function all(): array
    {
        return [
            new MarketDefinition('jvmoebel.de', 'de-DE', 'EUR', 'DE'),
            new MarketDefinition('jvmoebel.at', 'de-DE', 'EUR', 'AT'),
            new MarketDefinition('jvmoebel.ch', 'de-DE', 'CHF', 'CH'),
            new MarketDefinition('jvfurniture.co.uk', 'en-GB', 'GBP', 'GB'),
            new MarketDefinition('jvmobili.it', 'de-DE', 'EUR', 'IT'),
            new MarketDefinition('jvmeble.pl', 'de-DE', 'EUR', 'PL'),
        ];
    }
}
