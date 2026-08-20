<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Jv\Import\Service\Catalog\Dto\CatalogSchemaSnapshot;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class CleanupLegacyCatalogPropertyGroupsService
{
    private const int BATCH_SIZE = 500;

    /** @param EntityRepository<PropertyGroupCollection> $propertyGroupRepository */
    public function __construct(
        private Connection $connection,
        private EntityRepository $propertyGroupRepository,
    ) {
    }

    public function execute(CatalogSchemaSnapshot $snapshot, bool $dryRun, Context $context): int
    {
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $candidateIds = [];
        foreach ($snapshot->attributes as $attribute) {
            $legacyId = CatalogIdentity::legacyPropertyGroupId($attribute->name, $attribute->type, $attribute->multiValue);
            if ($legacyId !== CatalogIdentity::propertyGroupId($attribute->name)) {
                $candidateIds[$legacyId] = true;
            }
        }

        $unusedIds = $this->unusedLegacyPropertyGroupIds(array_keys($candidateIds));
        if (!$dryRun && [] !== $unusedIds) {
            foreach (array_chunk($unusedIds, self::BATCH_SIZE) as $ids) {
                $this->propertyGroupRepository->delete(array_map(static fn (string $id): array => ['id' => $id], $ids), $context);
            }
        }

        return count($unusedIds);
    }

    /**
     * @param list<string> $candidateIds
     *
     * @return list<string>
     */
    private function unusedLegacyPropertyGroupIds(array $candidateIds): array
    {
        if ([] === $candidateIds) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT LOWER(HEX(`property_group`.`id`))
                FROM `property_group`
                WHERE `property_group`.`id` IN (:ids)
                  AND NOT EXISTS (
                    SELECT 1
                    FROM `jv_catalog_category_attribute`
                    WHERE `jv_catalog_category_attribute`.`property_group_id` = `property_group`.`id`
                  )
                  AND NOT EXISTS (
                    SELECT 1
                    FROM `property_group_option`
                    LEFT JOIN `product_property`
                      ON `product_property`.`property_group_option_id` = `property_group_option`.`id`
                    LEFT JOIN `product_option`
                      ON `product_option`.`property_group_option_id` = `property_group_option`.`id`
                    LEFT JOIN `product_configurator_setting`
                      ON `product_configurator_setting`.`property_group_option_id` = `property_group_option`.`id`
                    WHERE `property_group_option`.`property_group_id` = `property_group`.`id`
                      AND (
                        `product_property`.`product_id` IS NOT NULL
                        OR `product_option`.`product_id` IS NOT NULL
                        OR `product_configurator_setting`.`id` IS NOT NULL
                      )
                  )
                SQL,
            ['ids' => array_map(Uuid::fromHexToBytes(...), $candidateIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        return $ids;
    }
}
