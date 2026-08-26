<?php declare(strict_types=1);

namespace Jv\Import\Subscriber\StoreApi;

use Jv\Import\Service\ProductDeliveryTime\AttachSalesChannelProductDeliveryTimesToStoreApiProductsService;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ProductDeliveryTimeSubscriber extends AbstractProductDetailRoute
{
    public function __construct(
        private readonly AbstractProductDetailRoute $decorated,
        private readonly AttachSalesChannelProductDeliveryTimesToStoreApiProductsService $attachDeliveryTimes,
    ) {
    }

    public function getDecorated(): AbstractProductDetailRoute
    {
        return $this->decorated;
    }

    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDetailRouteResponse
    {
        $response = $this->decorated->load($productId, $request, $context, $criteria);
        $this->attachDeliveryTimes->execute([$response->getProduct()], $context);

        return $response;
    }
}
