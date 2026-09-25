<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Backfill;

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
        return $this->connection->transactional(fn (): array => $this->backfill($context));
    }

    /** @return array{assigned: int, conflicts: list<string>, missingNames: list<string>} */
    private function backfill(Context $context): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT LOWER(HEX(source.product_id)) AS product_id, source.account, source.dataset,
                   source.factory_id AS external_id, run.factory_name
            FROM jv_aftercool_product_source source
            LEFT JOIN jv_aftercool_import_run run
              ON run.account = source.account AND run.dataset = source.dataset AND run.factory_id = source.factory_id
             AND run.status IN ('completed', 'completed_with_errors')
            ORDER BY source.product_id, source.factory_id, run.created_at DESC
            SQL);

        /** @var array<string, array<string, array{name: ?string, account: string, dataset: string}>> $byProduct */
        $byProduct = [];
        /** @var array<string, array{name: ?string, account: string, dataset: string}> $identities */
        $identities = [];
        foreach ($rows as $row) {
            $identity = $row['account'].':'.$row['dataset'].':'.$row['external_id'];
            $name = is_string($row['factory_name']) ? $row['factory_name'] : null;
            $identities[$identity] ??= ['name' => $name, 'account' => $row['account'], 'dataset' => $row['dataset']];
            if (null === $identities[$identity]['name'] && null !== $name) {
                $identities[$identity]['name'] = $name;
            }
            $byProduct[$row['product_id']][$identity] = $identities[$identity];
        }

        $missingNames = [];
        $factoryIds = [];
        foreach ($identities as $identity => $source) {
            [$account, $dataset, $externalId] = explode(':', $identity, 3);
            $namespace = 'aftercool:'.$account.':'.$dataset;
            $criteria = (new Criteria())->addFilter(new EqualsFilter('sourceNamespace', $namespace))->addFilter(new EqualsFilter('externalId', $externalId));
            $mapping = $this->factorySourceRepository->search($criteria, $context)->first();
            if (null !== $mapping) {
                $factoryIds[$identity] = $mapping->getFactoryId();
                if (null !== $source['name'] && '' !== trim($source['name'])) {
                    $this->factoryRepository->update([['id' => $mapping->getFactoryId(), 'name' => $source['name']]], $context);
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
                'externalId' => $externalId,
                'factoryId' => $factoryId,
            ]], $context);
            $factoryIds[$identity] = $factoryId;
        }

        $updates = [];
        $conflicts = [];
        foreach ($byProduct as $productId => $sources) {
            if (1 !== count($sources)) {
                $conflicts[] = $productId;
                continue;
            }
            $identity = array_key_first($sources);
            if (!isset($factoryIds[$identity])) {
                continue;
            }
            $updates[] = ['id' => $productId, 'jvFactoryId' => $factoryIds[$identity]];
        }

        foreach (array_chunk($updates, 100) as $chunk) {
            $this->productRepository->update($chunk, $context);
            $ids = array_column($chunk, 'id');
            $this->productIndexer->handle(new ProductIndexingMessage($ids, null, $context));
        }

        return ['assigned' => count($updates), 'conflicts' => $conflicts, 'missingNames' => $missingNames];
    }
}
