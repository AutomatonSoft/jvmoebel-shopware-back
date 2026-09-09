<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Route;

use Jv\Storefront\Service\StorefrontConfigLoader;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
final class StorefrontConfigRoute
{
    public function __construct(
        private readonly StorefrontConfigLoader $configLoader,
    ) {
    }

    public function getDecorated(): never
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/storefront-config',
        name: 'store-api.storefront.config',
        methods: ['GET'],
    )]
    public function load(Request $request, SalesChannelContext $context): StorefrontConfigRouteResponse
    {
        return new StorefrontConfigRouteResponse($this->configLoader->load($context));
    }
}
