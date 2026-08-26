<?php declare(strict_types=1);

namespace Jv\Import\Subscriber\StoreApi;

use Jv\Import\Service\ProductDeliveryTime\AttachSalesChannelProductDeliveryTimesToStoreApiProductsService;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class ProductListDeliveryTimeSubscriber extends AbstractProductListRoute
{
    public function __construct(
        private readonly AbstractProductListRoute $decorated,
        private readonly AttachSalesChannelProductDeliveryTimesToStoreApiProductsService $attachDeliveryTimes,
    ) {
    }

    public function getDecorated(): AbstractProductListRoute
    {
        return $this->decorated;
    }

    public function load(Criteria $criteria, SalesChannelContext $context): ProductListResponse
    {
        $response = $this->decorated->load($criteria, $context);
        $this->attachDeliveryTimes->execute($response->getProducts(), $context);

        return $response;
    }
}
