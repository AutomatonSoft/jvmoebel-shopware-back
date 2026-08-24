<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;

/**
 * Removes only relations owned by the OKB catalog import before the native
 * product serializer writes the current snapshot. Manual product relations
 * must never disappear as a side effect of a catalog rerun.
 */
final readonly class ReconcileCatalogProductImportRecordService
{
    public function __construct(private Connection $connection)
    {
    }

    /** @param array<string, mixed> $record */
    public function execute(array $record, string $recordType): void
    {
        $productId = $record['id'] ?? null;
        if (!is_string($productId)) {
            return;
        }
        $parameters = [
            'productId' => hex2bin($productId),
            'versionId' => hex2bin(Defaults::LIVE_VERSION),
            'sourceCode' => 'okb',
        ];
        $this->deleteCatalogPropertyRelations('product_property', $parameters);
        $this->deleteCatalogPropertyRelations('product_option', $parameters);
        if ('parent' === $recordType) {
            $this->connection->executeStatement(
                <<<'SQL'
                    DELETE `setting`
                    FROM `product_configurator_setting` `setting`
                    INNER JOIN `property_group_option` `option`
                      ON `option`.`id` = `setting`.`property_group_option_id`
                    WHERE `setting`.`product_id` = :productId
                      AND `setting`.`product_version_id` = :versionId
                      AND EXISTS (
                        SELECT 1
                        FROM `jv_catalog_category_attribute` `mapping`
                        WHERE `mapping`.`source_code` = :sourceCode
                          AND `mapping`.`property_group_id` = `option`.`property_group_id`
                      )
                    SQL,
                $parameters,
            );
            $this->connection->executeStatement(
                <<<'SQL'
                    DELETE `product_category`
                    FROM `product_category`
                    WHERE `product_category`.`product_id` = :productId
                      AND `product_category`.`product_version_id` = :versionId
                      AND EXISTS (
                        SELECT 1
                        FROM `jv_catalog_category_attribute` `mapping`
                        LEFT JOIN `category` `catalog_category`
                          ON `catalog_category`.`parent_id` = `mapping`.`category_id`
                         AND `catalog_category`.`parent_version_id` = `mapping`.`category_version_id`
                        WHERE `mapping`.`source_code` = :sourceCode
                          AND (
                            `product_category`.`category_id` = `mapping`.`category_id`
                            OR `product_category`.`category_id` = `catalog_category`.`id`
                          )
                      )
                    SQL,
                $parameters,
            );
        }
    }

    /** @param array{productId: string|false, versionId: string|false, sourceCode: string} $parameters */
    private function deleteCatalogPropertyRelations(string $table, array $parameters): void
    {
        $this->connection->executeStatement(
            sprintf(
                <<<'SQL'
                    DELETE `relation`
                    FROM `%s` `relation`
                    INNER JOIN `property_group_option` `option`
                      ON `option`.`id` = `relation`.`property_group_option_id`
                    WHERE `relation`.`product_id` = :productId
                      AND `relation`.`product_version_id` = :versionId
                      AND EXISTS (
                        SELECT 1
                        FROM `jv_catalog_category_attribute` `mapping`
                        WHERE `mapping`.`source_code` = :sourceCode
                          AND `mapping`.`property_group_id` = `option`.`property_group_id`
                      )
                    SQL,
                $table,
            ),
            $parameters,
        );
    }
}
