<?php

declare(strict_types=1);

namespace Jv\Cms\StoreApi\Search;

use Jv\Cms\Service\Search\Exception\SearchUnavailableException;
use Jv\Cms\Service\Search\JvProductSearchService;
use Jv\Cms\Service\Search\ProductSearchServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
final class JvSearchSuggestRoute
{
    public function __construct(
        private readonly ProductSearchServiceInterface $searchService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): never
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/jv-search/suggest',
        name: 'store-api.jv.search.suggest',
        methods: ['POST'],
    )]
    public function load(Request $request, SalesChannelContext $context): JvSearchSuggestRouteResponse
    {
        if (!$request->request->has('search') && !$request->query->has('search')) {
            throw new BadRequestHttpException('Parameter "search" is required.');
        }

        $search = RequestParamHelper::get($request, 'search');
        if (!\is_string($search) && !\is_int($search) && !\is_float($search)) {
            throw new BadRequestHttpException('Parameter "search" must be a string.');
        }

        $search = trim((string) $search);

        $limitRaw = RequestParamHelper::get($request, 'limit', JvProductSearchService::DEFAULT_SUGGEST_LIMIT);
        $limit = \is_numeric($limitRaw) ? (int) $limitRaw : JvProductSearchService::DEFAULT_SUGGEST_LIMIT;

        try {
            $result = $this->searchService->suggest($search, $limit, $context);
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (SearchUnavailableException $exception) {
            throw new ServiceUnavailableHttpException(null, $exception->getMessage(), $exception);
        } catch (\Throwable $exception) {
            $this->logger->error('jv-search suggest unexpected failure', [
                'operation' => 'jv_search_suggest_route',
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new ServiceUnavailableHttpException(null, 'Product suggest is temporarily unavailable.', $exception);
        }

        return new JvSearchSuggestRouteResponse($result);
    }
}
