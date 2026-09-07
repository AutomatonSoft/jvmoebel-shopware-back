<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Profile;

use Jv\Import\Integration\CosmoShop\Profile\CustomerImportProfile;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class CustomerImportProfileTest extends TestCase
{
    private const string CUSTOMER_GROUP_ID = 'cfbd5018d38d41d8adca10d94fc8bdd6';

    public function testItDefinesAStableCustomerImportProfile(): void
    {
        $definition = CustomerImportProfile::definition(Market::Germany, self::CUSTOMER_GROUP_ID);

        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.import-profile.jv_cosmoshop_customer_jvmoebel_de'),
            $definition['id'],
        );
        self::assertSame('jv_cosmoshop_customer_jvmoebel_de', $definition['technicalName']);
        self::assertSame('import', $definition['type']);
        self::assertSame('customer', $definition['sourceEntity']);
        self::assertSame('text/csv', $definition['fileType']);
        self::assertSame(';', $definition['delimiter']);
        self::assertSame('"', $definition['enclosure']);
        self::assertSame(['id'], $definition['updateBy']);
    }

    public function testItResolvesRequiredAssociationsByUuidDefaultsOnly(): void
    {
        $mapping = CustomerImportProfile::mapping(Market::Germany, self::CUSTOMER_GROUP_ID);
        $byMappedKey = array_column($mapping, null, 'mappedKey');

        self::assertSame($this->defaultMapping('group.id', 'customer_group', self::CUSTOMER_GROUP_ID), $this->withoutPosition($byMappedKey['customer_group']));
        self::assertSame($this->defaultMapping('language.id', 'language', Market::Germany->languageId()), $this->withoutPosition($byMappedKey['language']));
        self::assertSame($this->defaultMapping('salesChannel.id', 'sales_channel', Market::Germany->salesChannelId()), $this->withoutPosition($byMappedKey['sales_channel']));

        self::assertNotContains('group.translations.DEFAULT.name', array_column($mapping, 'key'));
        self::assertNotContains('language.locale.code', array_column($mapping, 'key'));
        self::assertNotContains('salesChannel.translations.DEFAULT.name', array_column($mapping, 'key'));
        self::assertNotContains('defaultPaymentMethod.translations.DEFAULT.name', array_column($mapping, 'key'));
    }

    public function testItMapsTheObservableCustomerAndDefaultAddressContract(): void
    {
        $mapping = CustomerImportProfile::mapping(Market::Germany, self::CUSTOMER_GROUP_ID);
        $mappedKeys = array_column($mapping, 'key', 'mappedKey');

        self::assertSame('id', $mappedKeys['id']);
        self::assertSame('customerNumber', $mappedKeys['customer_number']);
        self::assertSame('salutation.salutationKey', $mappedKeys['salutation']);
        self::assertSame('birthday', $mappedKeys['birthday']);
        self::assertSame('vatIds', $mappedKeys['vat_ids']);
        self::assertSame('customFields', $mappedKeys['custom_fields']);
        self::assertSame('accountType', $mappedKeys['account_type']);
        self::assertSame('defaultBillingAddress.id', $mappedKeys['billing_id']);
        self::assertSame('defaultBillingAddress.country.iso', $mappedKeys['billing_country']);
        self::assertSame('defaultShippingAddress.id', $mappedKeys['shipping_id']);
        self::assertSame('defaultShippingAddress.country.iso', $mappedKeys['shipping_country']);
    }

    /** @return array{key: string, mappedKey: string, useDefaultValue: true, defaultValue: string} */
    private function defaultMapping(string $key, string $mappedKey, string $defaultValue): array
    {
        return [
            'key' => $key,
            'mappedKey' => $mappedKey,
            'useDefaultValue' => true,
            'defaultValue' => $defaultValue,
        ];
    }

    /**
     * @param array<string, mixed> $mapping
     *
     * @return array<string, mixed>
     */
    private function withoutPosition(array $mapping): array
    {
        unset($mapping['position']);

        return $mapping;
    }
}
