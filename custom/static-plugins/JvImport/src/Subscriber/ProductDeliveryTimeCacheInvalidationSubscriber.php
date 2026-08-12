<?php declare(strict_types=1);

namespace Jv\Import\Subscriber;

use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeCacheTag;
use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeDefinition;
use Shopware\Core\Content\Product\Events\InvalidateProductCache;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ProductDeliveryTimeCacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CacheInvalidator $cacheInvalidator,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [EntityWrittenContainerEvent::class => 'invalidate'];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        $written = $event->getEventByEntityName(ProductSalesChannelDeliveryTimeDefinition::ENTITY_NAME);
        if (null === $written) {
            return;
        }

        $tags = array_map(ProductSalesChannelDeliveryTimeCacheTag::forLink(...), $written->getIds());
        $productIds = [];
        foreach ($written->getPayloads() as $payload) {
            if (isset($payload['productId']) && is_string($payload['productId'])) {
                $productIds[] = $payload['productId'];
            }
        }

        foreach ($productIds as $productId) {
            $tags[] = ProductDetailRoute::buildName($productId);
        }

        $this->cacheInvalidator->invalidate($tags);
        if ([] !== $productIds) {
            $this->eventDispatcher->dispatch(new InvalidateProductCache(array_values(array_unique($productIds))));
        }
    }
}
