<?php declare(strict_types=1);

namespace Jv\Import\Subscriber\StoreApi;

use Jv\Import\Service\ProductDeliveryTime\AttachSalesChannelProductDeliveryTimesToStoreApiProductsService;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ProductSearchDeliveryTimeSubscriber extends AbstractProductSearchRoute
{
    public function __construct(
        private readonly AbstractProductSearchRoute $decorated,
        private readonly AttachSalesChannelProductDeliveryTimesToStoreApiProductsService $attachDeliveryTimes,
    ) {
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {
        $response = $this->decorated->load($request, $context, $criteria);
        $this->attachDeliveryTimes->execute($response->getListingResult()->getEntities(), $context);

        return $response;
    }
}
