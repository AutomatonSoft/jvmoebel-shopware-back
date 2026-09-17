<?php declare(strict_types=1);

namespace Jv\Seo\StoreApi\Route;

use Jv\Seo\Service\Redirect\LookupRedirectService;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
final readonly class RedirectLookupRoute
{
    public function __construct(private LookupRedirectService $lookup)
    {
    }

    #[Route(path: '/store-api/jv-seo/redirect', name: 'store-api.jv-seo.redirect.lookup', methods: ['POST'])]
    public function load(Request $request, SalesChannelContext $context): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $url = is_array($payload) && is_string($payload['url'] ?? null) ? $payload['url'] : '';
            $data = $this->lookup->lookup($url, $context->getSalesChannelId(), $context->getContext());
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return new JsonResponse(['errors' => [[
                'code' => 'invalid_redirect_url',
                'detail' => $exception instanceof \JsonException ? 'Request body must be valid JSON.' : $exception->getMessage(),
            ]]], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['data' => $data]);
    }
}
