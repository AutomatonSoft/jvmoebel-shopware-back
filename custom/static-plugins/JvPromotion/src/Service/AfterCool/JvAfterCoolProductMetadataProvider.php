<?php declare(strict_types=1);

namespace Jv\Promotion\Service\AfterCool;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

final class JvAfterCoolProductMetadataProvider implements ResetInterface
{
    /** @var array<string, JvAfterCoolProductMetadata|null> */
    private array $cache = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getForProductId(string $productId): ?JvAfterCoolProductMetadata
    {
        if (\array_key_exists($productId, $this->cache)) {
            return $this->cache[$productId];
        }

        $row = $this->connection->fetchAssociative(
            'SELECT factory_id, stammartikel_id, source_file_prefix, source_ean
             FROM jv_aftercool_product_source
             WHERE product_id = :productId AND product_version_id = :versionId
             LIMIT 1',
            [
                'productId' => Uuid::fromHexToBytes($productId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
        );

        if (!\is_array($row)) {
            return $this->cache[$productId] = null;
        }

        return $this->cache[$productId] = new JvAfterCoolProductMetadata(
            (int) $row['factory_id'],
            isset($row['stammartikel_id']) ? (string) $row['stammartikel_id'] : null,
            isset($row['source_file_prefix']) ? (string) $row['source_file_prefix'] : null,
            isset($row['source_ean']) ? (string) $row['source_ean'] : null,
        );
    }

    /**
     * @param list<string> $productIds
     *
     * @return array<string, JvAfterCoolProductMetadata|null>
     */
    public function preload(array $productIds): array
    {
        $missing = [];
        foreach ($productIds as $productId) {
            if (!\array_key_exists($productId, $this->cache)) {
                $missing[] = $productId;
            }
        }

        if ([] === $missing) {
            return $this->cache;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(product_id)) AS product_id, factory_id, stammartikel_id, source_file_prefix, source_ean
             FROM jv_aftercool_product_source
             WHERE product_id IN (:productIds) AND product_version_id = :versionId',
            [
                'productIds' => Uuid::fromHexToBytesList($missing),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['productIds' => ArrayParameterType::BINARY],
        );

        foreach ($missing as $productId) {
            $this->cache[$productId] = null;
        }

        foreach ($rows as $row) {
            $productId = (string) $row['product_id'];
            $this->cache[$productId] = new JvAfterCoolProductMetadata(
                (int) $row['factory_id'],
                isset($row['stammartikel_id']) ? (string) $row['stammartikel_id'] : null,
                isset($row['source_file_prefix']) ? (string) $row['source_file_prefix'] : null,
                isset($row['source_ean']) ? (string) $row['source_ean'] : null,
            );
        }

        return $this->cache;
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
