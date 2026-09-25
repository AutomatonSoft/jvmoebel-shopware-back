<?php declare(strict_types=1);

namespace Jv\Import\Elasticsearch;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use OpenSearchDSL\BuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;

final class FactoryAwareProductDefinition extends AbstractElasticsearchDefinition
{
    public function __construct(
        private readonly AbstractElasticsearchDefinition $inner,
        private readonly Connection $connection,
    ) {
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->inner->getEntityDefinition();
    }

    public function getMapping(Context $context): array
    {
        $mapping = $this->inner->getMapping($context);
        $mapping['properties']['jvFactoryId'] = self::KEYWORD_FIELD;
        $mapping['properties']['jvFactory'] = ElasticsearchFieldBuilder::nested([
            'id' => self::KEYWORD_FIELD,
        ]);

        return $mapping;
    }

    public function getIterator(): ?IterableQuery
    {
        return $this->inner->getIterator();
    }

    public function buildTermQuery(Context $context, Criteria $criteria): BuilderInterface
    {
        return $this->inner->buildTermQuery($context, $criteria);
    }

    public function fetch(array $ids, Context $context): array
    {
        $documents = $this->inner->fetch($ids, $context);
        if ([] === $documents) {
            return [];
        }

        $factoryIds = $this->connection->fetchAllKeyValue(
            <<<'SQL'
                SELECT LOWER(HEX(product.id)) AS id,
                       LOWER(HEX(COALESCE(product.jv_factory_id, parent.jv_factory_id))) AS factory_id
                FROM product
                LEFT JOIN product parent
                  ON parent.id = product.parent_id AND parent.version_id = :versionId
                WHERE product.id IN (:ids) AND product.version_id = :versionId
                SQL,
            [
                'ids' => array_map(Uuid::fromHexToBytes(...), array_keys($documents)),
                'versionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ],
            ['ids' => ArrayParameterType::BINARY],
        );

        foreach ($documents as $id => &$document) {
            $factoryId = $factoryIds[$id] ?? null;
            $document['jvFactoryId'] = $factoryId;
            $document['jvFactory'] = null === $factoryId ? null : ['id' => $factoryId, '_count' => 1];
        }
        unset($document);

        return $documents;
    }
}
