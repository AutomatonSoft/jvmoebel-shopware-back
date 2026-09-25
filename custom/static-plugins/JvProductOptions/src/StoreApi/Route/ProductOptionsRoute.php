<?php declare(strict_types=1);

namespace Jv\ProductOptions\StoreApi\Route;

use Jv\ProductOptions\Service\OptionPricing\ProductOptionsLoader;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
final class ProductOptionsRoute
{
    /**
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly ProductOptionsLoader $optionsLoader,
    ) {
    }

    public function getDecorated(): never
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/jv-product-options/{productId}',
        name: 'store-api.jv-product-options',
        methods: ['GET']
    )]
    public function load(string $productId, SalesChannelContext $context): ProductOptionsRouteResponse
    {
        $criteria = new Criteria([$productId]);
        /** @var SalesChannelProductEntity|null $product */
        $product = $this->productRepository->search($criteria, $context)->get($productId);

        if (null === $product) {
            throw ProductException::productNotFound($productId);
        }

        $baseUnitPrice = $product->getCalculatedPrice()->getUnitPrice();

        return new ProductOptionsRouteResponse(
            $this->optionsLoader->load($productId, $baseUnitPrice, $context)
        );
    }
}
