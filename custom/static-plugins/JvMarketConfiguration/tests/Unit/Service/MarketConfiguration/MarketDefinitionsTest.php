<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Tests\Unit\Service\MarketConfiguration;

use Jv\MarketConfiguration\Service\MarketConfiguration\MarketDefinitions;
use PHPUnit\Framework\TestCase;

final class MarketDefinitionsTest extends TestCase
{
    public function testItDefinesEveryMarketExactlyOnce(): void
    {
        $markets = (new MarketDefinitions())->all();

        self::assertSame(
            [
                ['jvmoebel.de', 'de-DE', 'EUR', 'DE'],
                ['jvmoebel.at', 'de-DE', 'EUR', 'AT'],
                ['jvmoebel.ch', 'de-DE', 'CHF', 'CH'],
                ['jvfurniture.co.uk', 'en-GB', 'GBP', 'GB'],
                ['jvmobili.it', 'de-DE', 'EUR', 'IT'],
                ['jvmeble.pl', 'de-DE', 'EUR', 'PL'],
            ],
            array_map(
                static fn ($market): array => [
                    $market->domain,
                    $market->languageCode,
                    $market->currencyCode,
                    $market->countryCode,
                ],
                $markets,
            ),
        );

        $ids = array_map(static fn ($market): string => $market->salesChannelId(), $markets);
        self::assertCount(6, array_unique($ids));
        self::assertSame($ids, array_map(static fn ($market): string => $market->salesChannelId(), (new MarketDefinitions())->all()));

        $domainIds = array_map(static fn ($market): string => $market->salesChannelDomainId(), $markets);
        self::assertCount(6, array_unique($domainIds));
        self::assertSame($domainIds, array_map(static fn ($market): string => $market->salesChannelDomainId(), (new MarketDefinitions())->all()));
        self::assertSame(
            array_map(static fn ($market): string => 'https://'.$market->domain, $markets),
            array_map(static fn ($market): string => $market->url(), $markets),
        );
    }
}
