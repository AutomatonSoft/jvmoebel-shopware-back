<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Backfill;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Jv\Import\Core\Content\Factory\FactoryCollection;
use Jv\Import\Core\Content\FactorySource\FactorySourceCollection;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class BackfillProductFactoriesService
{
    private const BATCH_SIZE = 100;

    /**
     * @param EntityRepository<FactoryCollection>       $factoryRepository
     * @param EntityRepository<FactorySourceCollection> $factorySourceRepository
     * @param EntityRepository<ProductCollection>       $productRepository
     */
    public function __construct(
        private Connection $connection,
        private EntityRepository $factoryRepository,
        private EntityRepository $factorySourceRepository,
        private EntityRepository $productRepository,
        private ProductIndexer $productIndexer,
    ) {
    }

    /** @return array{assigned: int, conflicts: list<string>, missingNames: list<string>} */
    public function execute(Context $context): array
    {
        $result = ['assigned' => 0, 'conflicts' => [], 'missingNames' => []];
        $lastProductId = null;

        while (true) {
            $productIds = $this->nextProductIds($lastProductId);
            if ([] === $productIds) {
                break;
            }
            $lastProductId = Uuid::fromHexToBytes($productIds[array_key_last($productIds)]);

            $batch = $this->connection->transactional(fn (): array => $this->backfillBatch($productIds, $context));
            $result['assigned'] += $batch['assigned'];
            $result['conflicts'] = [...$result['conflicts'], ...$batch['conflicts']];
            $result['missingNames'] = [...$result['missingNames'], ...$batch['missingNames']];
        }

        return $result;
    }

    /** @return list<string> */
    private function nextProductIds(?string $lastProductId): array
    {
        $sql = 'SELECT DISTINCT LOWER(HEX(product_id)) FROM jv_aftercool_product_source';
        $parameters = [];
        $types = [];
        if (null !== $lastProductId) {
            $sql .= ' WHERE product_id > ?';
            $parameters[] = $lastProductId;
            $types[] = \Doctrine\DBAL\ParameterType::BINARY;
        }
        $sql .= ' ORDER BY product_id LIMIT '.self::BATCH_SIZE;

        /* @var list<string> */
        return $this->connection->fetchFirstColumn($sql, $parameters, $types);
    }

    /** @param list<string> $productIds
     * @return array{assigned: int, conflicts: list<string>, missingNames: list<string>} */
    private function backfillBatch(array $productIds, Context $context): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT LOWER(HEX(source.product_id)) AS product_id, source.account, source.dataset,
                   source.factory_id AS external_id, run.factory_name
            FROM jv_aftercool_product_source source
            LEFT JOIN jv_aftercool_import_run run
              ON run.account = source.account AND run.dataset = source.dataset AND run.factory_id = source.factory_id
             AND run.status IN ('completed', 'completed_with_errors')
            WHERE source.product_id IN (?)
            ORDER BY source.product_id, source.factory_id, run.created_at DESC
            SQL, [array_map(Uuid::fromHexToBytes(...), $productIds)], [ArrayParameterType::BINARY]);

        /** @var array<string, array<string, array{name: ?string, account: string, dataset: string}>> $byProduct */
        $byProduct = [];
        /** @var array<string, array{name: ?string, account: string, dataset: string, externalId: string}> $identities */
        $identities = [];
        foreach ($rows as $row) {
            $identity = json_encode([$row['account'], $row['dataset'], $row['external_id']], JSON_THROW_ON_ERROR);
            $name = is_string($row['factory_name']) ? $row['factory_name'] : null;
            $identities[$identity] ??= [
                'name' => $name,
                'account' => $row['account'],
                'dataset' => $row['dataset'],
                'externalId' => (string) $row['external_id'],
            ];
            if (null === $identities[$identity]['name'] && null !== $name) {
                $identities[$identity]['name'] = $name;
            }
            $byProduct[$row['product_id']][$identity] = [
                'name' => $identities[$identity]['name'],
                'account' => $row['account'],
                'dataset' => $row['dataset'],
            ];
        }

        $missingNames = [];
        $factoryIds = [];
        foreach ($identities as $identity => $source) {
            $namespace = 'aftercool:'.$source['account'].':'.$source['dataset'];
            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('sourceNamespace', $namespace))
                ->addFilter(new EqualsFilter('externalId', $source['externalId']));
            $mapping = $this->factorySourceRepository->search($criteria, $context)->first();
            if (null !== $mapping) {
                $factoryId = $mapping->getFactoryId();
                $factoryIds[$identity] = $factoryId;
                if (null !== $source['name'] && '' !== trim($source['name'])) {
                    $factory = $this->factoryRepository->search(new Criteria([$factoryId]), $context)->first();
                    if (null !== $factory && $factory->getName() !== $source['name']) {
                        $this->factoryRepository->update([['id' => $factoryId, 'name' => $source['name']]], $context);
                    }
                }
                continue;
            }
            if (null === $source['name'] || '' === trim($source['name'])) {
                $missingNames[] = $identity;
                continue;
            }

            $factoryId = Uuid::randomHex();
            $this->factoryRepository->create([['id' => $factoryId, 'name' => $source['name']]], $context);
            $this->factorySourceRepository->create([[
                'id' => Uuid::randomHex(),
                'sourceNamespace' => $namespace,
                'externalId' => $source['externalId'],
                'factoryId' => $factoryId,
            ]], $context);
            $factoryIds[$identity] = $factoryId;
        }

        $conflicts = [];
        $expectedFactoryIds = [];
        foreach ($byProduct as $productId => $sources) {
            if (1 !== count($sources)) {
                $conflicts[] = $productId;
                continue;
            }
            $identity = array_key_first($sources);
            if (isset($factoryIds[$identity])) {
                $expectedFactoryIds[$productId] = $factoryIds[$identity];
            }
        }

        $updates = [];
        if ([] !== $expectedFactoryIds) {
            $current = $this->connection->fetchAllKeyValue(
                'SELECT LOWER(HEX(id)), LOWER(HEX(jv_factory_id)) FROM product WHERE id IN (?) AND version_id = ?',
                [array_map(Uuid::fromHexToBytes(...), array_keys($expectedFactoryIds)), Uuid::fromHexToBytes($context->getVersionId())],
                [ArrayParameterType::BINARY, \Doctrine\DBAL\ParameterType::BINARY],
            );
            foreach ($expectedFactoryIds as $productId => $factoryId) {
                if (($current[$productId] ?? null) !== $factoryId) {
                    $updates[] = ['id' => $productId, 'jvFactoryId' => $factoryId];
                }
            }
        }

        if ([] !== $updates) {
            $this->productRepository->update($updates, $context);
            $this->productIndexer->handle(new ProductIndexingMessage(array_column($updates, 'id'), null, $context));
        }

        return ['assigned' => count($updates), 'conflicts' => $conflicts, 'missingNames' => array_values(array_unique($missingNames))];
    }
}
