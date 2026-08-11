<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductDeliveryTime;

use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeCacheTag;
use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class AttachSalesChannelProductDeliveryTimesToStoreApiProductsService
{
    public function __construct(
        private ResolveSalesChannelProductDeliveryTimesService $resolveDeliveryTimes,
        private CacheTagCollector $cacheTagCollector,
    ) {
    }

    /** @param iterable<ProductEntity> $products */
    public function execute(iterable $products, SalesChannelContext $context): void
    {
        $productsById = [];
        foreach ($products as $product) {
            $productsById[$product->getId()] = $product;
        }

        $deliveryTimes = $this->resolveDeliveryTimes->execute(
            array_keys($productsById),
            $context->getSalesChannelId(),
            $context->getContext(),
        );

        foreach ($deliveryTimes as $productId => $deliveryTime) {
            $product = $productsById[$productId];
            $marketDeliveryTime = $deliveryTime->getDeliveryTime();
            if (null === $marketDeliveryTime) {
                continue;
            }

            $product->setDeliveryTimeId($marketDeliveryTime->getId());
            $product->setDeliveryTime($marketDeliveryTime);
            $product->addExtension(
                'jvImportDeliveryTimes',
                new ProductSalesChannelDeliveryTimeCollection([$deliveryTime]),
            );
            $this->cacheTagCollector->addTag(ProductSalesChannelDeliveryTimeCacheTag::forLink($deliveryTime->getId()));
        }
    }
}
