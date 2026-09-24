<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

final class SearchPromotionProductsService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly JvPromotionProductQueryService $productQuery,
    ) {
    }

    /**
     * @return array{total: int, items: list<array<string, mixed>>, limit: int, offset: int}
     */
    public function execute(
        ?string $query,
        ?int $factoryId,
        ?string $collectionId,
        int $limit,
        int $offset,
    ): array {
        $parameters = [
            'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'limit' => $limit,
            'offset' => $offset,
        ];

        $filters = ['src.product_version_id = :versionId'];
        if (null !== $factoryId && $factoryId > 0) {
            $filters[] = 'src.factory_id = :factoryId';
            $parameters['factoryId'] = $factoryId;
        }
        if (null !== $collectionId && '' !== $collectionId) {
            $filters[] = 'src.stammartikel_id = :collectionId';
            $parameters['collectionId'] = $collectionId;
        }
        if (null !== $query && '' !== trim($query)) {
            $filters[] = '(src.source_ean LIKE :query OR src.collection_name LIKE :query OR p.product_number LIKE :query OR pt.name LIKE :query)';
            $parameters['query'] = '%'.trim($query).'%';
        }

        $where = implode(' AND ', $filters);

        $total = (int) $this->connection->fetchOne(
            'SELECT COUNT(DISTINCT src.product_id)
             FROM jv_aftercool_product_source src
             INNER JOIN product p ON p.id = src.product_id AND p.version_id = src.product_version_id
             LEFT JOIN product_translation pt ON pt.product_id = p.id AND pt.product_version_id = p.version_id AND pt.language_id = :languageId
             WHERE '.$where,
            $parameters,
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(src.product_id)) AS product_id,
                src.source_ean AS ean,
                COALESCE(pt.name, p.product_number) AS name,
                src.factory_id,
                COALESCE(src.factory_name, CONCAT(\'Factory \', src.factory_id)) AS factory_name,
                src.stammartikel_id AS collection_id,
                src.collection_name,
                pp.price AS base_price_json
             FROM jv_aftercool_product_source src
             INNER JOIN product p ON p.id = src.product_id AND p.version_id = src.product_version_id
             LEFT JOIN product_translation pt ON pt.product_id = p.id AND pt.product_version_id = p.version_id AND pt.language_id = :languageId
             LEFT JOIN product_price pp ON pp.product_id = p.id AND pp.product_version_id = p.version_id AND pp.quantity_start = 1 AND pp.rule_id IS NULL
             WHERE '.$where.'
             GROUP BY src.product_id, src.source_ean, pt.name, p.product_number, src.factory_id, src.factory_name, src.stammartikel_id, src.collection_name, pp.price
             ORDER BY name ASC
             LIMIT :limit OFFSET :offset',
            $parameters,
            [
                'limit' => ParameterType::INTEGER,
                'offset' => ParameterType::INTEGER,
            ],
        );

        $items = array_map(
            fn (array $row): array => $this->productQuery->mapProductRow($row),
            $rows,
        );

        return [
            'total' => $total,
            'items' => $items,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }
}
