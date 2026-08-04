<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Tests\Unit\Service\MarketConfiguration;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\TestCase;

final class MarketTest extends TestCase
{
    public function testItDefinesEveryMarketExactlyOnce(): void
    {
        $markets = Market::cases();

        self::assertSame(
            [
                ['jvmoebel.de', 'JVMöbel Deutschland', 'de-DE', 'EUR', 'DE'],
                ['jvmoebel.at', 'JVMöbel Österreich', 'de-DE', 'EUR', 'AT'],
                ['jvmoebel.ch', 'JVMöbel Schweiz', 'de-DE', 'CHF', 'CH'],
                ['jvfurniture.co.uk', 'JV Furniture', 'en-GB', 'GBP', 'GB'],
                ['jvmobili.it', 'JVMöbel Italia', 'de-DE', 'EUR', 'IT'],
                ['jvmeble.pl', 'JVMöbel Polska', 'de-DE', 'EUR', 'PL'],
            ],
            array_map(
                static fn (Market $market): array => [
                    $market->domain(),
                    $market->name(),
                    $market->languageCode(),
                    $market->currencyCode(),
                    $market->countryCode(),
                ],
                $markets,
            ),
        );

        $ids = array_map(static fn (Market $market): string => $market->salesChannelId(), $markets);
        self::assertCount(6, array_unique($ids));
        self::assertSame($ids, array_map(static fn (Market $market): string => $market->salesChannelId(), Market::cases()));

        $domainIds = array_map(static fn (Market $market): string => $market->salesChannelDomainId(), $markets);
        self::assertCount(6, array_unique($domainIds));
        self::assertSame($domainIds, array_map(static fn (Market $market): string => $market->salesChannelDomainId(), Market::cases()));
        self::assertSame(
            array_map(static fn (Market $market): string => 'https://'.$market->domain(), $markets),
            array_map(static fn (Market $market): string => $market->url(), $markets),
        );
    }

    public function testSalesChannelUrlTemplateIsEnvironmentSpecific(): void
    {
        $template = 'http://{domain}.localhost';

        self::assertSame(
            [
                'http://jvmoebel.de.localhost',
                'http://jvmoebel.at.localhost',
                'http://jvmoebel.ch.localhost',
                'http://jvfurniture.co.uk.localhost',
                'http://jvmobili.it.localhost',
                'http://jvmeble.pl.localhost',
            ],
            array_map(static fn (Market $market): string => $market->url($template), Market::cases()),
        );
        self::assertSame(
            Market::Germany->salesChannelId(),
            Market::Germany->salesChannelId(),
        );
        self::assertSame(
            Market::from('jvmoebel.de')->salesChannelId(),
            Market::Germany->salesChannelId(),
        );
    }
}
