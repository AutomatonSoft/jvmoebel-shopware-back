<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Profile;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Framework\Uuid\Uuid;

final class CustomerImportProfile
{
    public static function technicalName(Market $market): string
    {
        return 'jv_cosmoshop_customer_'.str_replace('.', '_', $market->domain());
    }

    /**
     * @return array{id: string, technicalName: string, type: string, sourceEntity: string, fileType: string, delimiter: string, enclosure: string, mapping: list<array{key: string, mappedKey: string, position: int, requiredByUser?: bool, useDefaultValue?: bool, defaultValue?: string}>, updateBy: list<string>, config: array{escape: string}}
     */
    public static function definition(Market $market, string $customerGroupId): array
    {
        $technicalName = self::technicalName($market);

        return [
            'id' => Uuid::fromStringToHex('jvmoebel.import-profile.'.$technicalName),
            'technicalName' => $technicalName,
            'type' => 'import',
            'sourceEntity' => 'customer',
            'fileType' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'mapping' => self::mapping($market, $customerGroupId),
            'updateBy' => ['id'],
            'config' => ['escape' => ''],
        ];
    }

    /** @return list<array{key: string, mappedKey: string, position: int, requiredByUser?: bool, useDefaultValue?: bool, defaultValue?: string}> */
    public static function mapping(Market $market, string $customerGroupId): array
    {
        $mapping = [
            ['key' => 'id', 'mappedKey' => 'id'],
            ['key' => 'customerNumber', 'mappedKey' => 'customer_number'],
            ['key' => 'salutation.salutationKey', 'mappedKey' => 'salutation'],
            ['key' => 'title', 'mappedKey' => 'title'],
            ['key' => 'firstName', 'mappedKey' => 'first_name'],
            ['key' => 'lastName', 'mappedKey' => 'last_name'],
            ['key' => 'email', 'mappedKey' => 'email'],
            ['key' => 'active', 'mappedKey' => 'active'],
            ['key' => 'guest', 'mappedKey' => 'guest'],
            ['key' => 'birthday', 'mappedKey' => 'birthday'],
            ['key' => 'vatIds', 'mappedKey' => 'vat_ids'],
            ['key' => 'customFields', 'mappedKey' => 'custom_fields'],
            ['key' => 'accountType', 'mappedKey' => 'account_type'],
            ['key' => 'defaultBillingAddress.id', 'mappedKey' => 'billing_id'],
            ['key' => 'defaultBillingAddress.salutation.salutationKey', 'mappedKey' => 'billing_salutation'],
            ['key' => 'defaultBillingAddress.title', 'mappedKey' => 'billing_title'],
            ['key' => 'defaultBillingAddress.firstName', 'mappedKey' => 'billing_first_name'],
            ['key' => 'defaultBillingAddress.lastName', 'mappedKey' => 'billing_last_name'],
            ['key' => 'defaultBillingAddress.company', 'mappedKey' => 'billing_company'],
            ['key' => 'defaultBillingAddress.street', 'mappedKey' => 'billing_street'],
            ['key' => 'defaultBillingAddress.zipcode', 'mappedKey' => 'billing_zipcode'],
            ['key' => 'defaultBillingAddress.city', 'mappedKey' => 'billing_city'],
            ['key' => 'defaultBillingAddress.country.iso', 'mappedKey' => 'billing_country'],
            ['key' => 'defaultBillingAddress.phoneNumber', 'mappedKey' => 'billing_phone_number'],
            ['key' => 'defaultShippingAddress.id', 'mappedKey' => 'shipping_id'],
            ['key' => 'defaultShippingAddress.salutation.salutationKey', 'mappedKey' => 'shipping_salutation'],
            ['key' => 'defaultShippingAddress.title', 'mappedKey' => 'shipping_title'],
            ['key' => 'defaultShippingAddress.firstName', 'mappedKey' => 'shipping_first_name'],
            ['key' => 'defaultShippingAddress.lastName', 'mappedKey' => 'shipping_last_name'],
            ['key' => 'defaultShippingAddress.company', 'mappedKey' => 'shipping_company'],
            ['key' => 'defaultShippingAddress.street', 'mappedKey' => 'shipping_street'],
            ['key' => 'defaultShippingAddress.zipcode', 'mappedKey' => 'shipping_zipcode'],
            ['key' => 'defaultShippingAddress.city', 'mappedKey' => 'shipping_city'],
            ['key' => 'defaultShippingAddress.country.iso', 'mappedKey' => 'shipping_country'],
            ['key' => 'defaultShippingAddress.phoneNumber', 'mappedKey' => 'shipping_phone_number'],
            ['key' => 'group.id', 'mappedKey' => 'customer_group', 'useDefaultValue' => true, 'defaultValue' => $customerGroupId],
            ['key' => 'language.id', 'mappedKey' => 'language', 'useDefaultValue' => true, 'defaultValue' => $market->languageId()],
            ['key' => 'salesChannel.id', 'mappedKey' => 'sales_channel', 'useDefaultValue' => true, 'defaultValue' => $market->salesChannelId()],
        ];

        return array_map(
            static fn (array $entry, int $position): array => [...$entry, 'position' => $position + 1],
            $mapping,
            array_keys($mapping),
        );
    }
}
