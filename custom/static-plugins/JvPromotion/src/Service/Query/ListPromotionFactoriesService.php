<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Query;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolFactory;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class ListPromotionFactoriesService
{
    private const CATALOG_CACHE_KEY = 'jv-promotion-aftercool-factories';

    private ?string $catalogWarning = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly ?AfterCoolProductSourceInterface $catalog = null,
        private readonly ?CacheInterface $cache = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly string $afterCoolUsername = '',
    ) {
    }

    public function catalogWarning(): ?string
    {
        return $this->catalogWarning;
    }

    /**
     * @return list<array{id: int, name: string, productCount: int}>
     */
    public function execute(?string $query = null, int $limit = 50): array
    {
        $this->catalogWarning = null;
        $indexed = $this->loadIndexed();
        $merged = $indexed;

        foreach ($this->loadCatalog() as $factory) {
            $existing = $merged[$factory['id']] ?? null;
            $merged[$factory['id']] = [
                'id' => $factory['id'],
                'name' => '' !== $factory['name'] ? $factory['name'] : ($existing['name'] ?? ('Factory '.$factory['id'])),
                'productCount' => $existing['productCount'] ?? 0,
            ];
        }

        $items = array_values($merged);
        $needle = null !== $query ? mb_strtolower(trim($query)) : '';
        if ('' !== $needle) {
            $items = array_values(array_filter(
                $items,
                static fn (array $item): bool => str_contains(mb_strtolower($item['name']), $needle)
                    || str_contains((string) $item['id'], $needle),
            ));
        }

        usort($items, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        if ($limit > 0) {
            $items = \array_slice($items, 0, $limit);
        }

        return $items;
    }

    /**
     * @return array<int, array{id: int, name: string, productCount: int}>
     */
    private function loadIndexed(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT
                factory_id AS id,
                MAX(COALESCE(factory_name, CONCAT(\'Factory \', factory_id))) AS name,
                COUNT(*) AS product_count
             FROM jv_aftercool_product_source
             GROUP BY factory_id',
        );

        $indexed = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $indexed[$id] = [
                'id' => $id,
                'name' => (string) $row['name'],
                'productCount' => (int) $row['product_count'],
            ];
        }

        return $indexed;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function loadCatalog(): array
    {
        if (null === $this->catalog) {
            return [];
        }

        if ('' === trim($this->afterCoolUsername)) {
            $this->catalogWarning = 'AfterCool login is not configured. Set AFTERCOOL_USERNAME and AFTERCOOL_PASSWORD in .env.local.';

            return [];
        }

        try {
            if (null === $this->cache) {
                return $this->fetchCatalog();
            }

            /** @var list<array{id: int, name: string}> $catalog */
            $catalog = $this->cache->get(self::CATALOG_CACHE_KEY, function (ItemInterface $item): array {
                $item->expiresAfter(300);

                return $this->fetchCatalog();
            });

            return $catalog;
        } catch (\Throwable $exception) {
            $this->rememberCatalogFailure($exception);

            return [];
        }
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function fetchCatalog(): array
    {
        if (null === $this->catalog) {
            return [];
        }

        return array_map(
            static fn (AfterCoolFactory $factory): array => [
                'id' => $factory->id,
                'name' => $factory->name,
            ],
            $this->catalog->getFactories(),
        );
    }

    private function rememberCatalogFailure(\Throwable $exception): void
    {
        $this->catalogWarning = 'AfterCool factory list could not be loaded.';
        $this->logger?->warning($this->catalogWarning, ['exception' => $exception]);
    }
}
