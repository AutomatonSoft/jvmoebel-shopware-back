<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetDefinition;
use Jv\Promotion\Service\Promotion\Dto\JvPromotionTargetInput;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

final class JvPromotionProductQueryService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param list<JvPromotionTargetInput> $targets
     *
     * @return list<string> product UUIDs (hex)
     */
    public function resolveProductIds(array $targets): array
    {
        if ([] === $targets) {
            return [];
        }

        $parts = [];
        $parameters = [];
        $index = 0;

        foreach ($targets as $target) {
            ++$index;
            $part = match ($target->type) {
                JvPromotionTargetDefinition::TARGET_FACTORY => $this->factoryPart($target, $index, $parameters),
                JvPromotionTargetDefinition::TARGET_COLLECTION => $this->collectionPart($target, $index, $parameters),
                JvPromotionTargetDefinition::TARGET_FACTORY_PREFIX => $this->prefixPart($target, $index, $parameters),
                JvPromotionTargetDefinition::TARGET_PRODUCT => $this->productPart($target, $index, $parameters),
                JvPromotionTargetDefinition::TARGET_EAN => $this->eanPart($target, $index, $parameters),
                default => null,
            };

            if (\is_string($part) && '' !== $part) {
                $parts[] = $part;
            }
        }

        if ([] === $parts) {
            return [];
        }

        $sql = 'SELECT DISTINCT LOWER(HEX(product_id)) AS product_id FROM jv_aftercool_product_source WHERE '.implode(' OR ', $parts);
        $rows = $this->connection->fetchFirstColumn($sql, $parameters);

        return array_values(array_filter(array_map('strval', $rows), static fn (string $value): bool => '' !== $value));
    }

    /**
     * @param list<string> $productIds
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function loadProductRows(
        array $productIds,
        ?string $query,
        ?int $factoryId,
        ?string $collectionId,
        int $limit,
        int $offset,
    ): array {
        if ([] === $productIds) {
            return ['rows' => [], 'total' => 0];
        }

        $parameters = [
            'productIds' => Uuid::fromHexToBytesList($productIds),
            'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'limit' => $limit,
            'offset' => $offset,
        ];

        $filters = ['src.product_id IN (:productIds)', 'src.product_version_id = :versionId'];
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

        $types = [
            'productIds' => ArrayParameterType::BINARY,
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ];
        $languageParameters = [...$parameters, 'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)];

        $total = (int) $this->connection->fetchOne(
            'SELECT COUNT(DISTINCT src.product_id)
             FROM jv_aftercool_product_source src
             INNER JOIN product p ON p.id = src.product_id AND p.version_id = src.product_version_id
             LEFT JOIN product_translation pt ON pt.product_id = p.id AND pt.product_version_id = p.version_id AND pt.language_id = :languageId
             WHERE '.$where,
            $languageParameters,
            $types,
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
            $languageParameters,
            $types,
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function factoryPart(JvPromotionTargetInput $target, int $index, array &$parameters): string
    {
        $key = 'factory_'.$index;
        $parameters[$key] = $target->factoryId;

        return 'factory_id = :'.$key;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function collectionPart(JvPromotionTargetInput $target, int $index, array &$parameters): string
    {
        $factoryKey = 'collection_factory_'.$index;
        $stammKey = 'collection_stamm_'.$index;
        $parameters[$factoryKey] = $target->factoryId;
        $parameters[$stammKey] = $target->stammartikelId;

        return '(factory_id = :'.$factoryKey.' AND stammartikel_id = :'.$stammKey.')';
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function prefixPart(JvPromotionTargetInput $target, int $index, array &$parameters): string
    {
        $key = 'prefix_'.$index;
        $parameters[$key] = $target->sourceFilePrefix;

        return 'source_file_prefix = :'.$key;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function productPart(JvPromotionTargetInput $target, int $index, array &$parameters): string
    {
        if (null !== $target->productId) {
            $key = 'product_'.$index;
            $parameters[$key] = Uuid::fromHexToBytes($target->productId);

            return 'product_id = :'.$key;
        }

        if (null !== $target->ean) {
            $key = 'ean_'.$index;
            $parameters[$key] = $target->ean;

            return 'source_ean = :'.$key;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function eanPart(JvPromotionTargetInput $target, int $index, array &$parameters): string
    {
        $key = 'ean_only_'.$index;
        $parameters[$key] = $target->ean;

        return 'source_ean = :'.$key;
    }

    /**
     * @param array<string, mixed> $row
     */
    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function mapProductRow(array $row): array
    {
        $basePrice = null;
        if (isset($row['base_price_json']) && \is_string($row['base_price_json'])) {
            $decoded = json_decode($row['base_price_json'], true);
            if (\is_array($decoded)) {
                foreach ($decoded as $price) {
                    if (\is_array($price) && isset($price['gross'])) {
                        $basePrice = (float) $price['gross'];
                        break;
                    }
                }
            }
        }

        return [
            'id' => (string) $row['product_id'],
            'productId' => (string) $row['product_id'],
            'ean' => isset($row['ean']) ? (string) $row['ean'] : null,
            'name' => (string) $row['name'],
            'factoryId' => (int) $row['factory_id'],
            'factoryName' => (string) $row['factory_name'],
            'collectionId' => isset($row['collection_id']) ? (string) $row['collection_id'] : null,
            'collectionName' => isset($row['collection_name']) ? (string) $row['collection_name'] : null,
            'basePrice' => $basePrice,
        ];
    }
}
