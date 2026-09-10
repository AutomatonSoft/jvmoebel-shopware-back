<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Customer;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerRecordMapper;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\TestCase;

final class CosmoShopCustomerRecordMapperTest extends TestCase
{
    public function testItMapsACompleteBusinessCustomerAndChoosesTheFirstShippingAddress(): void
    {
        $mapper = new CosmoShopCustomerRecordMapper(['1' => 'DE', '28' => 'AT', '46' => 'CH']);
        $row = $mapper->map(Market::Germany, $this->customer(), [
            $this->shippingAddress(17, 'Later street', '17'),
            $this->shippingAddress(9, 'First street', '9'),
        ]);

        self::assertSame(CosmoShopCustomerIdentity::customerId(Market::Germany, 42), $row['id']);
        self::assertSame('jvmoebel.de-42', $row['customer_number']);
        self::assertSame('mrs', $row['salutation']);
        self::assertSame('Ada', $row['first_name']);
        self::assertSame('Lovelace', $row['last_name']);
        self::assertSame('ada@example.test', $row['email']);
        self::assertSame('1', $row['active']);
        self::assertSame('0', $row['guest']);
        self::assertSame('business', $row['account_type']);
        self::assertSame('1985-02-03', $row['birthday']);
        self::assertSame('["DE123456789"]', $row['vat_ids']);
        self::assertSame('{"jv_cosmoshop_account_type":"amazon"}', $row['custom_fields']);
        self::assertArrayNotHasKey('kd_pwd', $row);
        self::assertArrayNotHasKey('kd_salt', $row);
        self::assertArrayNotHasKey('password', $row);
        self::assertArrayNotHasKey('legacy_password', $row);

        self::assertSame(CosmoShopCustomerIdentity::billingAddressId(Market::Germany, 42), $row['billing_id']);
        self::assertSame('Main street 12a', $row['billing_street']);
        self::assertSame('DE', $row['billing_country']);

        self::assertSame(CosmoShopCustomerIdentity::shippingAddressId(Market::Germany, 9), $row['shipping_id']);
        self::assertSame('First street 9', $row['shipping_street']);
        self::assertSame('AT', $row['shipping_country']);
    }

    public function testItUsesASeparateDeterministicShippingIdWhenShippingEqualsBilling(): void
    {
        $mapper = new CosmoShopCustomerRecordMapper(['1' => 'DE']);
        $row = $mapper->map(Market::Germany, $this->customer(), []);

        self::assertSame(
            CosmoShopCustomerIdentity::fallbackShippingAddressId(Market::Germany, 42),
            $row['shipping_id'],
        );
        self::assertNotSame($row['billing_id'], $row['shipping_id']);
        self::assertSame($row['billing_street'], $row['shipping_street']);
        self::assertSame($row['billing_country'], $row['shipping_country']);
    }

    public function testItDoesNotGuessUnknownCountriesOrActivateLockedCustomers(): void
    {
        $customer = $this->customer();
        $customer['kd_land'] = '999';
        $customer['kd_status'] = 'k';
        $customer['kd_is_locked'] = 1;
        $customer['kd_firma'] = '';
        $customer['kd_umstid'] = '';
        $customer['kd_account_type'] = '';
        $customer['kd_geburtsdatum'] = '31.02.2020';
        $customer['kd_anrede'] = 'd';

        $row = (new CosmoShopCustomerRecordMapper(['1' => 'DE']))->map(Market::Germany, $customer, []);

        self::assertSame('', $row['billing_country']);
        self::assertSame('', $row['shipping_country']);
        self::assertSame('0', $row['active']);
        self::assertSame('personal', $row['account_type']);
        self::assertSame('', $row['birthday']);
        self::assertSame('not_specified', $row['salutation']);
    }

    /** @return array<string, int|string|null> */
    private function customer(): array
    {
        return [
            'kd_id' => 42,
            'kd_anrede' => 'w',
            'kd_anrede_titel' => 'Dr.',
            'kd_vorname' => 'Ada',
            'kd_nachname' => 'Lovelace',
            'kd_firma' => 'Analytical Engines GmbH',
            'kd_strasse' => 'Main street',
            'kd_hausnr' => '12a',
            'kd_plz' => '10115',
            'kd_ort' => 'Berlin',
            'kd_land' => '1',
            'kd_mail' => 'ada@example.test',
            'kd_tel' => '+49 30 123456',
            'kd_status' => 'k',
            'kd_is_locked' => 0,
            'kd_account_type' => 'amazon',
            'kd_umstid' => 'DE123456789',
            'kd_geburtsdatum' => '03.02.1985',
            'kd_pwd' => 'synthetic-password-payload',
            'kd_salt' => 'synthetic-salt',
        ];
    }

    /** @return array<string, int|string> */
    private function shippingAddress(int $id, string $street, string $houseNumber): array
    {
        return [
            'adressen_id' => $id,
            'kunden_id' => 42,
            'typ' => 'lief',
            'geschlecht' => 'm',
            'vorname' => 'Charles',
            'nachname' => 'Babbage',
            'firma' => '',
            'strasse' => $street,
            'hausnr' => $houseNumber,
            'plz' => '1010',
            'ort' => 'Wien',
            'land_id' => 28,
            'tel' => '+43 1 123456',
        ];
    }
}
