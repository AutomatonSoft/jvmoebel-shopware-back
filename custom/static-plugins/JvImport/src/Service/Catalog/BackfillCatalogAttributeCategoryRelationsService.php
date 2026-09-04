<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class BackfillCatalogAttributeCategoryRelationsService
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function execute(): int
    {
        /** @var list<array{source_code: string, category_group_id: string}> $groups */
        $groups = $this->connection->fetchAllAssociative('SELECT DISTINCT `source_code`, `category_group_id` FROM `jv_catalog_category_attribute` WHERE `category_id` IS NULL');
        $updated = 0;

        foreach ($groups as $group) {
            $updated += $this->connection->executeStatement(
                'UPDATE `jv_catalog_category_attribute` SET `category_id` = :categoryId, `category_version_id` = :categoryVersionId WHERE `source_code` = :sourceCode AND `category_group_id` = :categoryGroupId AND `category_id` IS NULL',
                [
                    'categoryId' => Uuid::fromHexToBytes(CatalogIdentity::categoryGroupId($group['source_code'], $group['category_group_id'])),
                    'categoryVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'sourceCode' => $group['source_code'],
                    'categoryGroupId' => $group['category_group_id'],
                ],
            );
        }

        return $updated;
    }
}
