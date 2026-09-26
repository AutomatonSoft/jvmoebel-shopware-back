<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Query;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolMappedProduct;

final class ListPromotionCollectionsService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ?AfterCoolProductSourceInterface $catalog = null,
    ) {
    }

    /**
     * @return array{data: list<array{id: string, name: string, productCount: int}>, total: int}
     */
    public function execute(int $factoryId, ?string $query, int $limit, int $offset): array
    {
        $parameters = [
            'factoryId' => $factoryId,
            'limit' => $limit,
            'offset' => $offset,
        ];

        $filter = 'factory_id = :factoryId AND stammartikel_id IS NOT NULL AND collection_name IS NOT NULL';
        if (null !== $query && '' !== trim($query)) {
            $filter .= ' AND collection_name LIKE :query';
            $parameters['query'] = '%'.trim($query).'%';
        }

        $total = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM (
                SELECT 1
                FROM jv_aftercool_product_source
                WHERE '.$filter.'
                GROUP BY stammartikel_id, collection_name
             ) grouped',
            $parameters,
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT
                stammartikel_id AS id,
                collection_name AS name,
                COUNT(*) AS product_count
             FROM jv_aftercool_product_source
             WHERE '.$filter.'
             GROUP BY stammartikel_id, collection_name
             ORDER BY collection_name ASC
             LIMIT '.$limit.' OFFSET '.$offset,
            $parameters,
        );

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'productCount' => (int) $row['product_count'],
            ];
        }

        if ([] === $data && 0 === $total && null !== $this->catalog) {
            return $this->loadFromCatalog($factoryId, $query, $limit, $offset);
        }

        return ['data' => $data, 'total' => $total];
    }

    /**
     * @return array{data: list<array{id: string, name: string, productCount: int}>, total: int}
     */
    private function loadFromCatalog(int $factoryId, ?string $query, int $limit, int $offset): array
    {
        if (null === $this->catalog) {
            return ['data' => [], 'total' => 0];
        }

        $hasQuery = null !== $query && '' !== trim($query);
        $pageSize = min(100, max(1, $hasQuery ? max($limit, 25) : 100));
        $page = $this->catalog->getProductPage($factoryId, 0, $pageSize, $hasQuery ? trim($query) : null);
        /** @var array<string, array{id: string, name: string, productCount: int}> $grouped */
        $grouped = [];
        foreach ($page->products as $product) {
            $id = $product->stammartikelId;
            if (null === $id || '' === trim($id)) {
                continue;
            }

            $id = trim($id);
            if (!isset($grouped[$id])) {
                $grouped[$id] = [
                    'id' => $id,
                    'name' => $this->collectionLabel($product, $id),
                    'productCount' => 0,
                ];
            }
            ++$grouped[$id]['productCount'];
            $label = $this->collectionLabel($product, $id);
            if ($id !== $label) {
                $grouped[$id]['name'] = $label;
            }
        }

        $items = array_values($grouped);
        if (!$hasQuery) {
            usort($items, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));
        }
        $total = count($items);

        return [
            'data' => array_slice($items, $offset, $limit),
            'total' => $total,
        ];
    }

    private function collectionLabel(AfterCoolMappedProduct $product, string $id): string
    {
        if (null !== $product->collectionName && '' !== trim($product->collectionName)) {
            return trim($product->collectionName);
        }

        $name = trim($product->name);

        return '' !== $name ? $name : $id;
    }
}
