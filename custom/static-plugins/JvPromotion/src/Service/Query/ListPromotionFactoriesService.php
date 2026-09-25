<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Query;

use Doctrine\DBAL\Connection;

final class ListPromotionFactoriesService
{
    private const AFTERCOOL_LISTER_NAMESPACE = 'aftercool:JV:lister';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<array{id: int, name: string, productCount: int}>
     */
    public function execute(?string $query = null, int $limit = 50): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT
                CAST(fs.external_id AS UNSIGNED) AS id,
                f.name AS name,
                COUNT(DISTINCT src.product_id) AS product_count
             FROM jv_factory f
             INNER JOIN jv_factory_source fs
                 ON fs.factory_id = f.id
                AND fs.source_namespace = :namespace
             LEFT JOIN jv_aftercool_product_source src
                 ON src.factory_id = CAST(fs.external_id AS UNSIGNED)
             GROUP BY fs.external_id, f.id, f.name
             ORDER BY f.name ASC',
            ['namespace' => self::AFTERCOOL_LISTER_NAMESPACE],
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'productCount' => (int) $row['product_count'],
            ];
        }

        $needle = null !== $query ? mb_strtolower(trim($query)) : '';
        if ('' !== $needle) {
            $items = array_values(array_filter(
                $items,
                static fn (array $item): bool => str_contains(mb_strtolower($item['name']), $needle)
                    || str_contains((string) $item['id'], $needle),
            ));
        }

        if ($limit > 0) {
            $items = \array_slice($items, 0, $limit);
        }

        return $items;
    }
}
