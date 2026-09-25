<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\AfterCool;

use Doctrine\DBAL\Connection;
use Jv\Import\Core\Content\Factory\FactoryCollection;
use Jv\Import\Elasticsearch\FactoryAwareProductDefinition;
use OpenSearchDSL\BuilderInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;

final class FactoryAwareProductDefinitionTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testProductDefinitionAddsFactoryIdToTheOpenSearchMappingAndDocuments(): void
    {
        $context = Context::createDefaultContext();
        $definition = static::getContainer()->get(ElasticsearchRegistry::class)->get('product');
        self::assertInstanceOf(FactoryAwareProductDefinition::class, $definition);
        $mapping = $definition->getMapping($context);
        self::assertSame('keyword', $mapping['properties']['jvFactoryId']['type']);
        self::assertSame('nested', $mapping['properties']['jvFactory']['type']);

        /** @var EntityRepository<FactoryCollection> $factoryRepository */
        $factoryRepository = static::getContainer()->get('jv_factory.repository');
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');
        /** @var EntityRepository<TaxCollection> $taxRepository */
        $taxRepository = static::getContainer()->get('tax.repository');
        $factoryId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $factoryRepository->create([['id' => $factoryId, 'name' => 'Index factory']], $context);
        $taxId = $taxRepository->searchIds(new Criteria(), $context)->firstId();
        self::assertNotNull($taxId);
        $productRepository->create([[
            'id' => $productId,
            'productNumber' => 'FACTORY-INDEX-'.strtoupper(substr($productId, 0, 8)),
            'name' => 'Factory index test',
            'stock' => 1,
            'taxId' => $taxId,
            'jvFactoryId' => $factoryId,
            'price' => [['currencyId' => Defaults::CURRENCY, 'net' => 1.0, 'gross' => 1.19, 'linked' => false]],
        ]], $context);

        try {
            $builder = $this->createMock(BuilderInterface::class);
            $inner = new class(static::getContainer()->get(ProductDefinition::class), $productId, $builder) extends AbstractElasticsearchDefinition {
                public function __construct(
                    private EntityDefinition $entityDefinition,
                    private string $productId,
                    private BuilderInterface $builder,
                ) {
                }

                public function getEntityDefinition(): EntityDefinition
                {
                    return $this->entityDefinition;
                }

                public function getMapping(Context $context): array
                {
                    return ['properties' => ['id' => self::KEYWORD_FIELD]];
                }

                public function getIterator(): ?IterableQuery
                {
                    return null;
                }

                public function buildTermQuery(Context $context, Criteria $criteria): BuilderInterface
                {
                    return $this->builder;
                }

                public function fetch(array $ids, Context $context): array
                {
                    return [$this->productId => ['id' => $this->productId]];
                }
            };
            $definition = new FactoryAwareProductDefinition($inner, static::getContainer()->get(Connection::class));
            $documents = $definition->fetch([$productId], $context);
            self::assertSame($factoryId, $documents[$productId]['jvFactoryId'] ?? null);
            self::assertSame($factoryId, $documents[$productId]['jvFactory']['id'] ?? null);
        } finally {
            $productRepository->delete([['id' => $productId]], $context);
            $factoryRepository->delete([['id' => $factoryId]], $context);
        }
    }
}
