<?php declare(strict_types=1);

namespace Jv\Import\Subscriber\StoreApi;

use Jv\Import\Service\ProductDeliveryTime\AttachSalesChannelProductDeliveryTimesToStoreApiProductsService;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ProductListingDeliveryTimeSubscriber extends AbstractProductListingRoute
{
    public function __construct(
        private readonly AbstractProductListingRoute $decorated,
        private readonly AttachSalesChannelProductDeliveryTimesToStoreApiProductsService $attachDeliveryTimes,
    ) {
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decorated;
    }

    public function load(string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductListingRouteResponse
    {
        $response = $this->decorated->load($categoryId, $request, $context, $criteria);
        $this->attachDeliveryTimes->execute($response->getResult()->getEntities(), $context);

        return $response;
    }
}
