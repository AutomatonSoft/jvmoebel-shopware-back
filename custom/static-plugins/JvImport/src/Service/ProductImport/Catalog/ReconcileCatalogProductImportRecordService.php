<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class ReconcileCatalogProductImportRecordService
{
    public function __construct(private Connection $connection)
    {
    }

    /** @param array<string, mixed> $record */
    public function execute(array $record, string $recordType): void
    {
        $id = $record['id'] ?? null;
        if (!is_string($id) || !Uuid::isValid($id)) {
            return;
        }
        if ('parent' === $recordType) {
            $this->reconcile($id, 'category', $this->ids($record['categories'] ?? []));

            return;
        }
        if ('child' !== $recordType) {
            return;
        }
        $this->reconcile($id, 'property', $this->ids($record['properties'] ?? []));
        $this->reconcile($id, 'option', $this->ids($record['options'] ?? []));
        $parentId = $record['parentId'] ?? null;
        if (is_string($parentId) && Uuid::isValid($parentId)) {
            $this->reconcile($parentId, 'configurator', $this->ids($record['options'] ?? []));
        }
    }

    /** @param list<string> $wanted */
    private function reconcile(string $productId, string $type, array $wanted): void
    {
        $p = Uuid::fromHexToBytes($productId);
        $v = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        /** @var list<string> $old */
        $old = array_values(array_filter(
            $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`relation_id`)) FROM `jv_catalog_product_relation` WHERE `product_id`=:p AND `product_version_id`=:v AND `relation_type`=:t', ['p' => $p, 'v' => $v, 't' => $type]),
            is_string(...),
        ));
        $gone = array_values(array_diff($old, $wanted));
        if ([] !== $gone) {
            $table = match ($type) {
                'category' => 'product_category','property' => 'product_property','option' => 'product_option','configurator' => 'product_configurator_setting',
                default => throw new \LogicException(sprintf('Unsupported catalog relation type "%s".', $type)),
            };
            $column = 'category' === $type ? 'category_id' : 'property_group_option_id';
            $this->connection->executeStatement(sprintf('DELETE FROM `%s` WHERE `product_id`=:p AND `product_version_id`=:v AND `%s` IN (:ids)', $table, $column), ['p' => $p, 'v' => $v, 'ids' => array_map(Uuid::fromHexToBytes(...), $gone)], ['ids' => ArrayParameterType::BINARY]);
            $this->connection->executeStatement('DELETE FROM `jv_catalog_product_relation` WHERE `product_id`=:p AND `product_version_id`=:v AND `relation_type`=:t AND `relation_id` IN (:ids)', ['p' => $p, 'v' => $v, 't' => $type, 'ids' => array_map(Uuid::fromHexToBytes(...), $gone)], ['ids' => ArrayParameterType::BINARY]);
        }
        foreach ($wanted as $relationId) {
            $this->connection->executeStatement('INSERT IGNORE INTO `jv_catalog_product_relation` (`product_id`,`product_version_id`,`relation_type`,`relation_id`) VALUES (:p,:v,:t,:r)', ['p' => $p, 'v' => $v, 't' => $type, 'r' => Uuid::fromHexToBytes($relationId)]);
        }
    }

    /** @return list<string> */
    private function ids(mixed $relations): array
    {
        if (!is_array($relations)) {
            return [];
        }
        $ids = [];
        foreach ($relations as $relation) {
            $id = is_array($relation) ? ($relation['id'] ?? null) : null;
            if (is_string($id) && Uuid::isValid($id)) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }
}
