<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

final class Migration1770000008CreateOkbCatalogSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000008;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_import_okb_category_group_attribute` (
                `id` BINARY(16) NOT NULL,
                `category_group_id` VARCHAR(64) NOT NULL,
                `attribute_id` VARCHAR(64) NOT NULL,
                `attribute_name` VARCHAR(255) NOT NULL,
                `attribute_type` VARCHAR(64) NOT NULL,
                `feature_relevance` VARCHAR(255) NULL,
                `multi_value` TINYINT(1) NOT NULL,
                `storage` VARCHAR(32) NOT NULL,
                `property_group_id` BINARY(16) NULL,
                `custom_field_name` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.jv_import_okb_category_group_attribute.source` (`category_group_id`, `attribute_id`),
                CONSTRAINT `fk.jv_import_okb_category_group_attribute.property_group`
                    FOREIGN KEY (`property_group_id`) REFERENCES `property_group` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $customFieldSetId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field-set.internal-product-code'));
        $customFieldId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field.internal-product-code'));
        $relationId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field-set-relation.internal-product-code.product'));

        $connection->executeStatement(
            'INSERT INTO `custom_field_set` (`id`, `name`, `config`, `active`, `global`, `position`, `created_at`) VALUES (:id, :name, :config, 1, 1, 1, NOW(3)) ON DUPLICATE KEY UPDATE `config` = VALUES(`config`), `active` = VALUES(`active`), `global` = VALUES(`global`), `updated_at` = NOW(3)',
            [
                'id' => $customFieldSetId,
                'name' => 'jv_internal_product_data',
                'config' => json_encode(['label' => ['de-DE' => 'JVMöbel interne Daten', 'en-GB' => 'JVMöbel internal data']], \JSON_THROW_ON_ERROR),
            ],
        );
        $connection->executeStatement(
            'INSERT INTO `custom_field` (`id`, `name`, `type`, `config`, `active`, `set_id`, `include_in_search`, `created_at`) VALUES (:id, :name, :type, :config, 1, :setId, 1, NOW(3)) ON DUPLICATE KEY UPDATE `config` = VALUES(`config`), `active` = VALUES(`active`), `set_id` = VALUES(`set_id`), `include_in_search` = VALUES(`include_in_search`), `updated_at` = NOW(3)',
            [
                'id' => $customFieldId,
                'name' => 'jv_internal_article_code',
                'type' => 'text',
                'setId' => $customFieldSetId,
                'config' => json_encode([
                    'label' => ['de-DE' => 'Interner Code / Notiz', 'en-GB' => 'Internal code / note'],
                    'customFieldType' => 'text',
                    'componentName' => 'sw-field',
                    'customFieldPosition' => 1,
                ], \JSON_THROW_ON_ERROR),
            ],
        );
        $connection->executeStatement(
            'INSERT INTO `custom_field_set_relation` (`id`, `set_id`, `entity_name`, `created_at`) VALUES (:id, :setId, :entityName, NOW(3)) ON DUPLICATE KEY UPDATE `updated_at` = NOW(3)',
            ['id' => $relationId, 'setId' => $customFieldSetId, 'entityName' => 'product'],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
