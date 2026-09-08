<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Customer;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerAddressRecordMapper;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\TestCase;

final class CosmoShopCustomerAddressRecordMapperTest extends TestCase
{
    public function testItMapsOneSourceAddressIntoTheProtectedPostImportContract(): void
    {
        $row = (new CosmoShopCustomerAddressRecordMapper(['1' => 'DE', '28' => 'AT']))->map(
            Market::Germany,
            [
                'adressen_id' => 17,
                'kunden_id' => 42,
                'typ' => 'lief',
                'geschlecht' => 'w',
                'vorname' => 'Ada',
                'nachname' => 'Lovelace',
                'firma' => 'Analytical Engines GmbH',
                'strasse' => 'Main street',
                'hausnr' => '12a',
                'plz' => '1010',
                'ort' => 'Wien',
                'land_id' => 28,
                'tel' => '+43 1 123456',
            ],
        );

        self::assertSame('42', $row['source_customer_id']);
        self::assertSame('17', $row['source_address_id']);
        self::assertSame('mrs', $row['salutation']);
        self::assertSame('', $row['title']);
        self::assertSame('Ada', $row['first_name']);
        self::assertSame('Lovelace', $row['last_name']);
        self::assertSame('Analytical Engines GmbH', $row['company']);
        self::assertSame('Main street 12a', $row['street']);
        self::assertSame('1010', $row['zipcode']);
        self::assertSame('Wien', $row['city']);
        self::assertSame('AT', $row['country']);
        self::assertSame('+43 1 123456', $row['phone_number']);
        self::assertArrayNotHasKey('id', $row);
        self::assertArrayNotHasKey('customer_id', $row);
    }

    public function testItDoesNotGuessAnUnknownCountryOrUnsupportedSalutation(): void
    {
        $row = (new CosmoShopCustomerAddressRecordMapper(['1' => 'DE']))->map(Market::Germany, [
            'adressen_id' => 18,
            'kunden_id' => 43,
            'typ' => 'lief',
            'geschlecht' => 'd',
            'land_id' => 999,
        ]);

        self::assertSame('not_specified', $row['salutation']);
        self::assertSame('', $row['country']);
    }
}
