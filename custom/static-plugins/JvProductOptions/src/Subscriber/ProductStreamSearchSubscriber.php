<?php declare(strict_types=1);

namespace Jv\ProductOptions\Subscriber;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ProductStreamSearchSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ElasticsearchEntitySearcherSearchEvent::class => 'onSearch',
        ];
    }

    public function onSearch(ElasticsearchEntitySearcherSearchEvent $event): void
    {
        if (ProductDefinition::ENTITY_NAME !== $event->getDefinition()->getEntityName()) {
            return;
        }

        $criteria = $event->getCriteria();
        if (!$this->isProductStreamIdSearch($criteria)) {
            return;
        }

        $event->getSearch()->setSource(false);
    }

    private function isProductStreamIdSearch(Criteria $criteria): bool
    {
        return $criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE)
            && null === $criteria->getLimit()
            && [] === $criteria->getSorting()
            && [] === $criteria->getAssociations()
            && [] === $criteria->getFields()
            && null === $criteria->getIncludes()
            && null === $criteria->getExcludes()
            && [] === $criteria->getQueries()
            && [] === $criteria->getPostFilters()
            && null === $criteria->getTerm();
    }
}
