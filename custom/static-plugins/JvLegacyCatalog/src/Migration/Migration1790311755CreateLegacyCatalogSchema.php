<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1790311755CreateLegacyCatalogSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1790311755;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS jv_legacy_catalog_source (
                id BINARY(16) NOT NULL,
                source_system VARCHAR(64) NOT NULL,
                source_project VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                sales_channel_id BINARY(16) NOT NULL,
                format_version INT NOT NULL,
                categories_sha256 CHAR(64) NOT NULL,
                category_count INT NOT NULL,
                content_count INT NOT NULL,
                seo_count INT NOT NULL,
                imported_at DATETIME(3) NOT NULL,
                created_at DATETIME(3) NOT NULL,
                updated_at DATETIME(3) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_jv_legacy_catalog_source_project (source_project),
                KEY idx_jv_legacy_catalog_source_sales_channel (sales_channel_id),
                CONSTRAINT fk_jv_legacy_catalog_source_sales_channel FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS jv_legacy_category (
                id BINARY(16) NOT NULL,
                source_id BINARY(16) NOT NULL,
                source_category_id BIGINT NOT NULL,
                source_parent_id BIGINT NOT NULL,
                parent_id BINARY(16) NULL,
                rubric_order INT NULL,
                display_name VARCHAR(512) NOT NULL,
                source_category_number VARCHAR(255) NULL,
                url_key VARCHAR(512) NULL,
                rubric_data JSON NOT NULL,
                created_at DATETIME(3) NOT NULL,
                updated_at DATETIME(3) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_jv_legacy_category_source_category (source_id, source_category_id),
                UNIQUE KEY uniq_jv_legacy_category_source_id_id (source_id, id),
                KEY idx_jv_legacy_category_source_parent (source_id, source_parent_id),
                KEY idx_jv_legacy_category_source_parent_id (source_id, parent_id),
                KEY idx_jv_legacy_category_display_name (display_name(191)),
                KEY idx_jv_legacy_category_number (source_category_number),
                KEY idx_jv_legacy_category_url_key (url_key(191)),
                CONSTRAINT fk_jv_legacy_category_source FOREIGN KEY (source_id)
                    REFERENCES jv_legacy_catalog_source (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_jv_legacy_category_parent FOREIGN KEY (source_id, parent_id)
                    REFERENCES jv_legacy_category (source_id, id) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS jv_legacy_category_content (
                id BINARY(16) NOT NULL,
                category_id BINARY(16) NOT NULL,
                source_language VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                rubnam LONGTEXT NULL,
                rubtext LONGTEXT NULL,
                rubtext_kurz LONGTEXT NULL,
                urlkey LONGTEXT NULL,
                page_title LONGTEXT NULL,
                meta_description LONGTEXT NULL,
                meta_keywords LONGTEXT NULL,
                raw_data JSON NOT NULL,
                created_at DATETIME(3) NOT NULL,
                updated_at DATETIME(3) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_jv_legacy_category_content_language (category_id, source_language),
                CONSTRAINT fk_jv_legacy_category_content_category FOREIGN KEY (category_id)
                    REFERENCES jv_legacy_category (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
