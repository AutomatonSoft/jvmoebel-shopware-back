<?php declare(strict_types=1);

namespace Jv\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

final class Migration1771000001CreateStorefrontConfigSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1771000001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_storefront_social_link` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NOT NULL,
                `url` VARCHAR(2048) NOT NULL,
                `icon_media_id` BINARY(16) NOT NULL,
                `position` INT NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `open_in_new_tab` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.jv_storefront_social_link.sales_channel` (`sales_channel_id`, `active`, `position`),
                CONSTRAINT `fk.jv_storefront_social_link.sales_channel`
                    FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_storefront_social_link.media`
                    FOREIGN KEY (`icon_media_id`) REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `jv_storefront_payment_badge` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NOT NULL,
                `icon_media_id` BINARY(16) NOT NULL,
                `position` INT NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.jv_storefront_payment_badge.sales_channel` (`sales_channel_id`, `active`, `position`),
                CONSTRAINT `fk.jv_storefront_payment_badge.sales_channel`
                    FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.jv_storefront_payment_badge.media`
                    FOREIGN KEY (`icon_media_id`) REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $customFieldSetId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field-set.storefront-config'));
        $relationId = Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.custom-field-set-relation.storefront-config.sales-channel'));

        $connection->executeStatement(
            'INSERT INTO `custom_field_set` (`id`, `name`, `config`, `active`, `global`, `position`, `created_at`) VALUES (:id, :name, :config, 1, 1, 1, NOW(3)) ON DUPLICATE KEY UPDATE `config` = VALUES(`config`), `active` = VALUES(`active`), `global` = VALUES(`global`), `updated_at` = NOW(3)',
            [
                'id' => $customFieldSetId,
                'name' => 'jv_storefront_config',
                'config' => json_encode(['label' => ['de-DE' => 'Storefront-Konfiguration', 'en-GB' => 'Storefront configuration']], \JSON_THROW_ON_ERROR),
            ],
        );

        $connection->executeStatement(
            'INSERT INTO `custom_field_set_relation` (`id`, `set_id`, `entity_name`, `created_at`) VALUES (:id, :setId, :entityName, NOW(3)) ON DUPLICATE KEY UPDATE `updated_at` = NOW(3)',
            ['id' => $relationId, 'setId' => $customFieldSetId, 'entityName' => 'sales_channel'],
        );

        $this->upsertCustomField($connection, $customFieldSetId, 'jvmoebel.custom-field.storefront-logo-media', 'jv_storefront_logo_media_id', 'text', [
            'label' => ['de-DE' => 'Storefront-Logo', 'en-GB' => 'Storefront logo'],
            'customFieldType' => 'text',
            'componentName' => 'sw-media-field',
            'customFieldPosition' => 1,
        ]);

        $this->upsertCustomField($connection, $customFieldSetId, 'jvmoebel.custom-field.footer-about-eyebrow', 'jv_footer_about_eyebrow', 'text', [
            'label' => ['de-DE' => 'Footer About Eyebrow', 'en-GB' => 'Footer about eyebrow'],
            'customFieldType' => 'text',
            'componentName' => 'sw-field',
            'customFieldPosition' => 2,
        ]);

        $this->upsertCustomField($connection, $customFieldSetId, 'jvmoebel.custom-field.footer-about-title', 'jv_footer_about_title', 'text', [
            'label' => ['de-DE' => 'Footer About Titel', 'en-GB' => 'Footer about title'],
            'customFieldType' => 'text',
            'componentName' => 'sw-field',
            'customFieldPosition' => 3,
        ]);

        $this->upsertCustomField($connection, $customFieldSetId, 'jvmoebel.custom-field.footer-about-description', 'jv_footer_about_description', 'text', [
            'label' => ['de-DE' => 'Footer About Beschreibung', 'en-GB' => 'Footer about description'],
            'customFieldType' => 'text',
            'componentName' => 'sw-text-editor',
            'customFieldPosition' => 4,
        ]);

        $this->upsertCustomField($connection, $customFieldSetId, 'jvmoebel.custom-field.footer-copyright-text', 'jv_footer_copyright_text', 'text', [
            'label' => ['de-DE' => 'Copyright-Text', 'en-GB' => 'Copyright text'],
            'customFieldType' => 'text',
            'componentName' => 'sw-field',
            'customFieldPosition' => 5,
        ]);

        $this->upsertCustomField($connection, $customFieldSetId, 'jvmoebel.custom-field.footer-revocation-enabled', 'jv_footer_revocation_enabled', 'bool', [
            'label' => ['de-DE' => 'Widerruf aktiv', 'en-GB' => 'Revocation enabled'],
            'customFieldType' => 'switch',
            'componentName' => 'sw-field',
            'customFieldPosition' => 6,
        ]);

        $this->upsertCustomField($connection, $customFieldSetId, 'jvmoebel.custom-field.footer-revocation-button-label', 'jv_footer_revocation_button_label', 'text', [
            'label' => ['de-DE' => 'Widerruf-Button', 'en-GB' => 'Revocation button label'],
            'customFieldType' => 'text',
            'componentName' => 'sw-field',
            'customFieldPosition' => 7,
        ]);

        $this->upsertCustomField($connection, $customFieldSetId, 'jvmoebel.custom-field.footer-revocation-recipient-email', 'jv_footer_revocation_recipient_email', 'text', [
            'label' => ['de-DE' => 'Widerruf E-Mail', 'en-GB' => 'Revocation recipient email'],
            'customFieldType' => 'text',
            'componentName' => 'sw-field',
            'customFieldPosition' => 8,
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
