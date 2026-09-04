<?php declare(strict_types=1);

namespace Jv\Import\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

final class Migration1770000012CreateCatalogProductAttributesField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770000012;
    }

    public function update(Connection $connection): void
    {
        $setId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field-set.internal-product-code'));
        $fieldId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field.catalog-attributes'));
        $connection->executeStatement(
            'INSERT INTO `custom_field` (`id`, `name`, `type`, `config`, `active`, `set_id`, `include_in_search`, `created_at`) VALUES (:id, :name, :type, :config, 1, :setId, 0, NOW(3)) ON DUPLICATE KEY UPDATE `type` = VALUES(`type`), `config` = VALUES(`config`), `active` = VALUES(`active`), `set_id` = VALUES(`set_id`), `include_in_search` = VALUES(`include_in_search`), `updated_at` = NOW(3)',
            [
                'id' => $fieldId,
                'name' => 'jv_catalog_attributes',
                'type' => 'json',
                'setId' => $setId,
                'config' => json_encode([
                    'label' => ['de-DE' => 'Katalogattribute', 'en-GB' => 'Catalog attributes'],
                    'customFieldType' => 'json',
                    'componentName' => 'sw-code-editor',
                    'customFieldPosition' => 2,
                ], \JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
