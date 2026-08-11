<?php declare(strict_types=1);

namespace Jv\Import\Subscriber\StoreApi;

use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeCollection;
use Jv\Import\Core\Content\ProductSalesChannelDeliveryTime\ProductSalesChannelDeliveryTimeEntity;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ProductDeliveryTimeSubscriber extends AbstractProductDetailRoute
{
    /** @param EntityRepository<ProductSalesChannelDeliveryTimeCollection> $deliveryTimeRepository */
    public function __construct(
        private readonly AbstractProductDetailRoute $decorated,
        private readonly EntityRepository $deliveryTimeRepository,
    ) {
    }

    public function getDecorated(): AbstractProductDetailRoute
    {
        return $this->decorated;
    }

    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDetailRouteResponse
    {
        $response = $this->decorated->load($productId, $request, $context, $criteria);
        $product = $response->getProduct();
        $deliveryTimeLink = $this->deliveryTimeRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('productId', $product->getId()))
                ->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()))
                ->addAssociation('deliveryTime')
                ->setLimit(1),
            $context->getContext(),
        )->first();

        if ($deliveryTimeLink instanceof ProductSalesChannelDeliveryTimeEntity) {
            $product->addExtension('jvImportDeliveryTimes', new ProductSalesChannelDeliveryTimeCollection([$deliveryTimeLink]));
        }

        return $response;
    }
}
