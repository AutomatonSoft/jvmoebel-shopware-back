<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductDeliveryTime;

use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeCollection;
use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

final readonly class ResolveSalesChannelProductDeliveryTimesService
{
    /** @param EntityRepository<ProductSalesChannelDeliveryTimeCollection> $deliveryTimeRepository */
    public function __construct(private EntityRepository $deliveryTimeRepository)
    {
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

        return $resolved;
    }
}
