<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/** Creates the documented, idempotent metadata contract for historical orders. */
final class Migration1770000021CreateCosmoShopOrderFields extends MigrationStep
{
    private const string SET_NAME = 'jv_cosmoshop_order_import';

    public function getCreationTimestamp(): int
    {
        return 1770000021;
    }

    public function update(Connection $connection): void
    {
        $setId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field-set.cosmoshop-order-import'));
        $connection->executeStatement(
            'INSERT INTO custom_field_set (id, name, config, active, global, position, created_at) VALUES (:id, :name, :config, 1, 1, 100, NOW(3)) ON DUPLICATE KEY UPDATE config = VALUES(config), active = 1, global = 1, updated_at = NOW(3)',
            ['id' => $setId, 'name' => self::SET_NAME, 'config' => json_encode(['label' => ['en-GB' => 'CosmoShop historical order import', 'de-DE' => 'CosmoShop historische Bestellimporte']], \JSON_THROW_ON_ERROR)],
        );

        $fields = [
            'jv_cosmoshop_historical_import' => 'bool',
            'jv_cosmoshop_source_market' => 'text',
            'jv_cosmoshop_source_order_id' => 'int',
            'jv_cosmoshop_source_customer_id' => 'int',
            'jv_cosmoshop_source_checksum' => 'text',
            'jv_cosmoshop_mapping_version' => 'text',
            'jv_cosmoshop_source_created_at' => 'datetime',
            'jv_cosmoshop_source_submitted_at' => 'datetime',
            'jv_cosmoshop_source_paid_at' => 'datetime',
            'jv_cosmoshop_source_price_display' => 'text',
            'jv_cosmoshop_source_vat_type' => 'text',
            'jv_cosmoshop_tax_status' => 'text',
            'jv_cosmoshop_status_history' => 'json',
            'jv_cosmoshop_packing_addresses' => 'json',
            'jv_cosmoshop_mail_artifact_present' => 'bool',
            'jv_cosmoshop_source_address_id' => 'int',
            'jv_cosmoshop_source_address_type' => 'text',
            'jv_cosmoshop_source_salutation' => 'text',
            'jv_cosmoshop_source_state' => 'text',
            'jv_cosmoshop_payment_key' => 'text',
            'jv_cosmoshop_payment_label' => 'text',
            'jv_cosmoshop_payment_source_plugin' => 'text',
            'jv_cosmoshop_transaction_reference' => 'text',
            'jv_cosmoshop_shipping_key' => 'text',
            'jv_cosmoshop_shipping_label' => 'text',
            'jv_cosmoshop_shipping_source_carrier_id' => 'int',
        ];
        foreach ($fields as $name => $type) {
            $fieldId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field.'.$name));
            $connection->executeStatement(
                'INSERT INTO custom_field (id, name, type, config, active, set_id, include_in_search, created_at) VALUES (:id, :name, :type, :config, 1, :setId, 0, NOW(3)) ON DUPLICATE KEY UPDATE type = VALUES(type), config = VALUES(config), active = 1, set_id = VALUES(set_id), updated_at = NOW(3)',
                ['id' => $fieldId, 'name' => $name, 'type' => $type, 'config' => json_encode(['customFieldType' => $type], \JSON_THROW_ON_ERROR), 'setId' => $setId],
            );
        }
        foreach (['order', 'order_transaction', 'order_delivery', 'order_address'] as $entityName) {
            $relationId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field-relation.cosmoshop-order-import.'.$entityName));
            $connection->executeStatement(
                'INSERT INTO custom_field_set_relation (id, set_id, entity_name, created_at) VALUES (:id, :setId, :entityName, NOW(3)) ON DUPLICATE KEY UPDATE set_id = VALUES(set_id), updated_at = NOW(3)',
                ['id' => $relationId, 'setId' => $setId, 'entityName' => $entityName],
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
