<?php declare(strict_types=1);

namespace Jv\Promotion\Migration;

use Doctrine\DBAL\Connection;
use Jv\Promotion\JvPromotionConstants;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

final class Migration1772000002CreateJvManagedPromotionCustomField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772000002;
    }

    public function update(Connection $connection): void
    {
        $customFieldSetId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field-set.promotion-meta'));
        $relationId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field-set-relation.promotion-meta.promotion'));

        $connection->executeStatement(
            'INSERT INTO `custom_field_set` (`id`, `name`, `config`, `active`, `global`, `position`, `created_at`) VALUES (:id, :name, :config, 1, 1, 100, NOW(3)) ON DUPLICATE KEY UPDATE `config` = VALUES(`config`), `active` = VALUES(`active`), `global` = VALUES(`global`), `updated_at` = NOW(3)',
            [
                'id' => $customFieldSetId,
                'name' => 'jv_promotion_meta',
                'config' => json_encode(['label' => ['de-DE' => 'JVMöbel Promotion', 'en-GB' => 'JVMöbel promotion']], \JSON_THROW_ON_ERROR),
            ],
        );

        $connection->executeStatement(
            'INSERT INTO `custom_field_set_relation` (`id`, `set_id`, `entity_name`, `created_at`) VALUES (:id, :setId, :entityName, NOW(3)) ON DUPLICATE KEY UPDATE `updated_at` = NOW(3)',
            ['id' => $relationId, 'setId' => $customFieldSetId, 'entityName' => 'promotion'],
        );

        $fieldId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field.promotion-jv-managed'));

        $connection->executeStatement(
            'INSERT INTO `custom_field` (`id`, `name`, `type`, `config`, `active`, `set_id`, `include_in_search`, `created_at`) VALUES (:id, :name, :type, :config, 1, :setId, 0, NOW(3)) ON DUPLICATE KEY UPDATE `config` = VALUES(`config`), `active` = VALUES(`active`), `set_id` = VALUES(`set_id`), `updated_at` = NOW(3)',
            [
                'id' => $fieldId,
                'name' => JvPromotionConstants::MANAGED_CUSTOM_FIELD,
                'type' => 'bool',
                'setId' => $customFieldSetId,
                'config' => json_encode([
                    'label' => ['de-DE' => 'JVMöbel verwaltet', 'en-GB' => 'JVMöbel managed'],
                    'customFieldType' => 'checkbox',
                    'componentName' => 'sw-field',
                    'customFieldPosition' => 1,
                ], \JSON_THROW_ON_ERROR),
            ],
        );
    }
}
