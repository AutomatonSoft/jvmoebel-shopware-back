<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Import;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Jv\Import\Core\Content\AfterCoolProductSource\AfterCoolProductSourceCollection;
use Jv\Import\Integration\AfterCool\Contract\AfterCoolApiClientInterface;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductItem;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolListerProductMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

final readonly class BackfillAfterCoolPromotionIndexService
{
    /** @param EntityRepository<AfterCoolProductSourceCollection> $sourceRepository */
    public function __construct(
        private Connection $connection,
        private AfterCoolApiClientInterface $client,
        private AfterCoolListerProductMapper $productMapper,
        private EntityRepository $sourceRepository,
    ) {
    }

    public function execute(int $limit = 100): int
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(`id`)) AS id, `factory_id`, `source_product_id`
                FROM `jv_aftercool_product_source`
                WHERE `stammartikel_id` IS NULL
                   OR `collection_name` IS NULL
                   OR `source_file` IS NULL
                   OR `source_file_prefix` IS NULL
                ORDER BY `updated_at` ASC, `id` ASC
                LIMIT :limit
                SQL,
            ['limit' => $limit],
            ['limit' => ParameterType::INTEGER],
        );

        if ([] === $rows) {
            return 0;
        }

        $updates = [];
        foreach ($rows as $row) {
            $payload = $this->resolvePromotionIndex((int) $row['factory_id'], (string) $row['source_product_id']);
            if (null === $payload) {
                continue;
            }

            $updates[] = [
                'id' => (string) $row['id'],
                ...$payload,
            ];
        }

        if ([] === $updates) {
            return 0;
        }

        $this->sourceRepository->update($updates, Context::createDefaultContext());

        return \count($updates);
    }

    /** @return array<string, string|null>|null */
    private function resolvePromotionIndex(int $factoryId, string $sourceProductId): ?array
    {
        $page = $this->client->getProductPage($factoryId, 0, 1, $sourceProductId);
        $item = $page->items[0] ?? null;
        if (!$item instanceof AfterCoolProductItem) {
            return null;
        }

        $linkedProduct = null;
        $stammartikel = $item->row['I_stammartikel'] ?? null;
        if (is_string($stammartikel) && '' !== trim($stammartikel)) {
            $linkedProduct = $this->client->getLinkedProduct(trim($stammartikel));
        }

        $mapped = $this->productMapper->map($item, $linkedProduct, true);

        return [
            'stammartikelId' => $mapped->stammartikelId,
            'collectionName' => $mapped->collectionName,
            'sourceFile' => $mapped->sourceFile,
            'sourceFilePrefix' => $mapped->sourceFilePrefix,
            'sourceRegion' => $mapped->sourceRegion,
        ];
    }
}
