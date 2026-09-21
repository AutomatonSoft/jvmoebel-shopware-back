<?php declare(strict_types=1);

namespace Jv\ProductOptions\Subscriber;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

final readonly class ProductStreamEntitySearcherDecorator implements EntitySearcherInterface
{
    public function __construct(
        private EntitySearcherInterface $decorated,
    ) {
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if ($context->getSource() instanceof AdminApiSource
            && ProductDefinition::ENTITY_NAME === $definition->getEntityName()
            && $this->isProductStreamIdSearch($criteria)
        ) {
            $context = $this->createSystemContext($context);
        }

        return $this->decorated->search($definition, $criteria, $context);
    }

    private function isProductStreamIdSearch(Criteria $criteria): bool
    {
        return $criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE)
            && null === $criteria->getLimit()
            && null === $criteria->getOffset()
            && [] === $criteria->getSorting()
            && [] === $criteria->getAssociations()
            && [] === $criteria->getFields()
            && null === $criteria->getIncludes()
            && null === $criteria->getExcludes()
            && [] === $criteria->getQueries()
            && [] === $criteria->getPostFilters()
            && [] === $criteria->getAggregations()
            && [] === $criteria->getGroupFields()
            && null === $criteria->getTerm();
    }

    private function createSystemContext(Context $context): Context
    {
        $systemContext = new Context(
            new SystemSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            $context->getCurrencyFactor(),
            $context->considerInheritance(),
            $context->getTaxState(),
            $context->getRounding(),
        );
        $systemContext->addState(...$context->getStates());

        return $systemContext;
    }
}
