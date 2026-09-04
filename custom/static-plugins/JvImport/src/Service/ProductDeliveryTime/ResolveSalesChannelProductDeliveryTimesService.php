<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductDeliveryTime;

use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeCollection;
use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

final readonly class ResolveSalesChannelProductDeliveryTimesService
{
    /**
     * @param EntityRepository<ProductSalesChannelDeliveryTimeCollection> $deliveryTimeRepository
     * @param EntityRepository<ProductCollection>                         $productRepository
     */
    public function __construct(
        private EntityRepository $deliveryTimeRepository,
        private EntityRepository $productRepository,
    ) {
    }

    /**
     * @param list<string> $productIds
     *
     * @return array<string, ProductSalesChannelDeliveryTimeEntity>
     */
    public function execute(array $productIds, string $salesChannelId, Context $context): array
    {
        if ([] === $productIds) {
            return [];
        }

        $links = $this->deliveryTimeRepository->search(
            (new Criteria())
                ->addFilter(new EqualsAnyFilter('productId', array_values(array_unique($productIds))))
                ->addFilter(new EqualsFilter('productVersionId', Defaults::LIVE_VERSION))
                ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
                ->addAssociation('deliveryTime'),
            $context,
        );

        $resolved = [];
        foreach ($links as $link) {
            $resolved[$link->getProductId()] = $link;
        }

        $unresolvedProductIds = array_keys(array_diff_key(array_flip($productIds), $resolved));
        if ([] === $unresolvedProductIds) {
            return $resolved;
        }

        $childrenByParentId = [];
        foreach ($this->productRepository->search(new Criteria($unresolvedProductIds), $context) as $product) {
            if (null !== $product->getParentId()) {
                $childrenByParentId[$product->getParentId()][] = $product->getId();
            }
        }
        if ([] === $childrenByParentId) {
            return $resolved;
        }

        $parentLinks = $this->deliveryTimeRepository->search(
            (new Criteria())
                ->addFilter(new EqualsAnyFilter('productId', array_keys($childrenByParentId)))
                ->addFilter(new EqualsFilter('productVersionId', Defaults::LIVE_VERSION))
                ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
                ->addAssociation('deliveryTime'),
            $context,
        );
        foreach ($parentLinks as $parentLink) {
            foreach ($childrenByParentId[$parentLink->getProductId()] ?? [] as $childId) {
                $resolved[$childId] = $parentLink;
            }
        }

        return $resolved;
    }
}
