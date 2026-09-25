<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Query;

use Doctrine\DBAL\Connection;

final class ListPromotionCollectionsService
{
    public function __construct(
        private readonly Connection $connection,
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

        return ['data' => $data, 'total' => $total];
    }
}
