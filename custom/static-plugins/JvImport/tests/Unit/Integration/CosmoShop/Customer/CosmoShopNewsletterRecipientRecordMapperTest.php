<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Customer;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopNewsletterRecipientRecordMapper;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CosmoShopNewsletterRecipientRecordMapperTest extends TestCase
{
    private const string TOKEN_KEY = 'newsletter-import-token-key-32bytes';

    #[DataProvider('statusProvider')]
    public function testItMapsEveryConfirmedSourceStatus(string $sourceStatus, ?string $confirmedAt, string $expectedStatus): void
    {
        $row = (new CosmoShopNewsletterRecipientRecordMapper(self::TOKEN_KEY))->map(
            Market::Germany,
            $this->recipient($sourceStatus, $confirmedAt),
        );

        self::assertSame($expectedStatus, $row['status']);
    }

    /** @return iterable<string, array{string, string|null, string}> */
    public static function statusProvider(): iterable
    {
        yield 'active and confirmed' => ['a', '2021-04-05 12:13:14', 'optIn'];
        yield 'active without confirmation' => ['a', null, 'direct'];
        yield 'active with zero confirmation date' => ['a', '0000-00-00 00:00:00', 'direct'];
        yield 'unsubscribed' => ['d', null, 'optOut'];
        yield 'pending/unknown consent' => ['p', null, 'notSet'];
        yield 'unknown source state remains invalid for Shopware' => ['unexpected', null, 'unexpected'];
    }

    public function testItCreatesStableMarketScopedIdentityAndAnIndependentUnpredictableToken(): void
    {
        $source = $this->recipient('a', '2021-04-05 12:13:14');
        $source['email'] = ' Customer@Example.TEST ';
        $source['customer_password'] = 'must-not-be-reused';
        $mapper = new CosmoShopNewsletterRecipientRecordMapper(self::TOKEN_KEY);

        $first = $mapper->map(Market::Germany, $source);
        $second = $mapper->map(Market::Germany, $source);
        $otherKey = (new CosmoShopNewsletterRecipientRecordMapper('different-newsletter-token-key-32b'))->map(Market::Germany, $source);

        self::assertSame(CosmoShopCustomerIdentity::newsletterRecipientId(Market::Germany, 'customer@example.test'), $first['id']);
        self::assertSame('customer@example.test', $first['email']);
        self::assertSame($first, $second);
        self::assertSame(64, strlen($first['hash']));
        self::assertTrue(ctype_xdigit($first['hash']));
        self::assertNotSame($first['hash'], $otherKey['hash']);
        self::assertNotSame($source['customer_password'], $first['hash']);
        self::assertArrayNotHasKey('customer_password', $first);
        self::assertSame(Market::Germany->salesChannelId(), $first['sales_channel_id']);
    }

    public function testItMapsProfileFieldsWithoutInventingUnsupportedData(): void
    {
        $row = (new CosmoShopNewsletterRecipientRecordMapper(self::TOKEN_KEY))->map(
            Market::Germany,
            $this->recipient('a', null),
        );

        self::assertSame('mr', $row['salutation']);
        self::assertSame('', $row['title']);
        self::assertSame('Alan', $row['first_name']);
        self::assertSame('Turing', $row['last_name']);
        self::assertSame('10115', $row['zip_code']);
        self::assertSame('Berlin', $row['city']);
        self::assertSame('Test street 1', $row['street']);
        self::assertArrayNotHasKey('confirmed_at', $row);
        self::assertArrayNotHasKey('phone', $row);
        self::assertArrayNotHasKey('company', $row);
    }

    public function testItRejectsAWeakTokenKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CosmoShopNewsletterRecipientRecordMapper('too-short');
    }

    /** @return array<string, string|null> */
    private function recipient(string $status, ?string $confirmedAt): array
    {
        return [
            'email' => 'alan@example.test',
            'status' => $status,
            'datum_confirm' => $confirmedAt,
            'anrede' => 'm',
            'anrede_titel' => '',
            'vorname' => 'Alan',
            'nachname' => 'Turing',
            'plz' => '10115',
            'ort' => 'Berlin',
            'strasse' => 'Test street 1',
            'telefon' => '+49 30 123456',
            'firma' => 'Computing Ltd.',
        ];
    }
}
