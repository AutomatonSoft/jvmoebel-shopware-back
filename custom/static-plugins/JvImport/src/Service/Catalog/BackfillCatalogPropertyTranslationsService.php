<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;

final readonly class BackfillCatalogPropertyTranslationsService
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function execute(): int
    {
        $parameters = ['systemLanguageId' => Defaults::LANGUAGE_SYSTEM];

        return $this->connection->executeStatement(<<<'SQL'
            INSERT INTO `property_group_translation` (`property_group_id`, `language_id`, `name`, `created_at`)
            SELECT DISTINCT `property_group`.`id`, `language`.`id`, `source_translation`.`name`, NOW(3)
            FROM `property_group`
            INNER JOIN `jv_catalog_category_attribute`
                ON `jv_catalog_category_attribute`.`property_group_id` = `property_group`.`id`
            INNER JOIN `property_group_translation` AS `source_translation`
                ON `source_translation`.`property_group_id` = `property_group`.`id`
                AND `source_translation`.`language_id` = UNHEX(:systemLanguageId)
            INNER JOIN `language`
            LEFT JOIN `property_group_translation` AS `translation`
                ON `translation`.`property_group_id` = `property_group`.`id`
                AND `translation`.`language_id` = `language`.`id`
            WHERE `translation`.`property_group_id` IS NULL
            SQL, $parameters)
            + $this->connection->executeStatement(<<<'SQL'
            INSERT INTO `property_group_option_translation` (`property_group_option_id`, `language_id`, `name`, `created_at`)
            SELECT DISTINCT `option`.`id`, `language`.`id`, `source_translation`.`name`, NOW(3)
            FROM `property_group_option` AS `option`
            INNER JOIN `jv_catalog_category_attribute`
                ON `jv_catalog_category_attribute`.`property_group_id` = `option`.`property_group_id`
            INNER JOIN `property_group_option_translation` AS `source_translation`
                ON `source_translation`.`property_group_option_id` = `option`.`id`
                AND `source_translation`.`language_id` = UNHEX(:systemLanguageId)
            INNER JOIN `language`
            LEFT JOIN `property_group_option_translation` AS `translation`
                ON `translation`.`property_group_option_id` = `option`.`id`
                AND `translation`.`language_id` = `language`.`id`
            WHERE `translation`.`property_group_option_id` IS NULL
            SQL, $parameters);
    }
}
