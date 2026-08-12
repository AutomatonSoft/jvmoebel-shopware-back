<?php declare(strict_types=1);

namespace Jv\Import\Subscriber\StoreApi;

use Jv\Import\Service\ProductDeliveryTime\AttachSalesChannelProductDeliveryTimesToStoreApiProductsService;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ProductCrossSellingDeliveryTimeSubscriber extends AbstractProductCrossSellingRoute
{
    public function __construct(
        private readonly AbstractProductCrossSellingRoute $decorated,
        private readonly AttachSalesChannelProductDeliveryTimesToStoreApiProductsService $attachDeliveryTimes,
    ) {
    }

    public function getDecorated(): AbstractProductCrossSellingRoute
    {
        return $this->decorated;
    }

    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductCrossSellingRouteResponse
    {
        $response = $this->decorated->load($productId, $request, $context, $criteria);
        $products = [];
        foreach ($response->getResult() as $crossSelling) {
            foreach ($crossSelling->getProducts() as $product) {
                $products[] = $product;
            }
        }
        $this->attachDeliveryTimes->execute($products, $context);

        return $response;
    }
}
