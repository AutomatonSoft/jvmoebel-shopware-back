<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Query;

use Doctrine\DBAL\Connection;

final class ListPromotionFactoryPrefixesService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<array{prefix: string, factoryIds: list<int>, productCount: int}>
     */
    public function execute(?string $query = null): array
    {
        $parameters = ['empty' => ''];
        $filter = 'WHERE source_file_prefix IS NOT NULL AND source_file_prefix != :empty';
        if (null !== $query && '' !== trim($query)) {
            $filter .= ' AND source_file_prefix LIKE :query';
            $parameters['query'] = '%'.trim($query).'%';
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT
                source_file_prefix AS prefix,
                GROUP_CONCAT(DISTINCT factory_id ORDER BY factory_id) AS factory_ids,
                COUNT(*) AS product_count
             FROM jv_aftercool_product_source
             '.$filter.'
             GROUP BY source_file_prefix
             ORDER BY source_file_prefix ASC',
            $parameters,
        );

        $result = [];
        foreach ($rows as $row) {
            $factoryIds = array_map('intval', array_filter(explode(',', (string) $row['factory_ids']), static fn (string $value): bool => '' !== $value));

            $result[] = [
                'prefix' => (string) $row['prefix'],
                'factoryIds' => $factoryIds,
                'productCount' => (int) $row['product_count'],
            ];
        }

        return $result;
    }
}
