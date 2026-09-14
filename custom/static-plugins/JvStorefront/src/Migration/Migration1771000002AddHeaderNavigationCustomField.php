<?php declare(strict_types=1);

namespace Jv\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

final class Migration1771000002AddHeaderNavigationCustomField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1771000002;
    }

    public function update(Connection $connection): void
    {
        $customFieldSetId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field-set.storefront-config'));

        $this->upsertCustomField($connection, $customFieldSetId, 'jvmoebel.custom-field.header-navigation-visible-category-ids', 'jv_header_navigation_visible_category_ids', 'json', [
            'label' => ['de-DE' => 'Header-Navigation sichtbare Kategorien', 'en-GB' => 'Header navigation visible categories'],
            'customFieldType' => 'json',
            'componentName' => 'sw-field',
            'customFieldPosition' => 9,
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    private function upsertCustomField(
        Connection $connection,
        string $setId,
        string $stableIdSeed,
        string $name,
        string $type,
        array $config,
    ): void {
        $fieldId = Uuid::fromHexToBytes(Uuid::fromStringToHex($stableIdSeed));

        $connection->executeStatement(
            'INSERT INTO `custom_field` (`id`, `name`, `type`, `config`, `active`, `set_id`, `include_in_search`, `created_at`) VALUES (:id, :name, :type, :config, 1, :setId, 0, NOW(3)) ON DUPLICATE KEY UPDATE `config` = VALUES(`config`), `active` = VALUES(`active`), `set_id` = VALUES(`set_id`), `updated_at` = NOW(3)',
            [
                'id' => $fieldId,
                'name' => $name,
                'type' => $type,
                'setId' => $setId,
                'config' => json_encode($config, \JSON_THROW_ON_ERROR),
            ],
        );
    }
}
