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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
final class JvSearchRoute
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
        path: '/store-api/jv-search',
        name: 'store-api.jv.search',
        methods: ['POST'],
    )]
    public function load(Request $request, SalesChannelContext $context): JvSearchRouteResponse
    {
        if (!$request->request->has('search') && !$request->query->has('search')) {
            throw new BadRequestHttpException('Parameter "search" is required.');
        }

        $search = RequestParamHelper::get($request, 'search');
        if (!\is_string($search) && !\is_int($search) && !\is_float($search)) {
            throw new BadRequestHttpException('Parameter "search" must be a string.');
        }

        $search = trim((string) $search);
        if ('' === $search) {
            throw new BadRequestHttpException('Parameter "search" must not be empty.');
        }

        $pageRaw = RequestParamHelper::get($request, 'page', 1);
        $limitRaw = RequestParamHelper::get($request, 'limit', JvProductSearchService::DEFAULT_PAGE_LIMIT);
        $page = \is_numeric($pageRaw) ? (int) $pageRaw : 1;
        $limit = \is_numeric($limitRaw) ? (int) $limitRaw : JvProductSearchService::DEFAULT_PAGE_LIMIT;

        $orderRaw = RequestParamHelper::get($request, 'order');
        $order = \is_string($orderRaw) ? trim($orderRaw) : null;
        if ('' === $order) {
            $order = null;
        }

        try {
            $result = $this->searchService->search(
                $search,
                $page,
                $limit,
                $this->readOptionIds($request),
                $context,
                $order,
            );
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (SearchUnavailableException $exception) {
            throw new ServiceUnavailableHttpException(null, $exception->getMessage(), $exception);
        } catch (\Throwable $exception) {
            $this->logger->error('jv-search unexpected failure', [
                'operation' => 'jv_search_route',
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new ServiceUnavailableHttpException(null, 'Product search is temporarily unavailable.', $exception);
        }

        return new JvSearchRouteResponse($result);
    }

    /**
     * @return list<string>
     */
    private function readOptionIds(Request $request): array
    {
        $raw = RequestParamHelper::get($request, 'properties', []);
        if (\is_string($raw)) {
            $raw = array_values(array_filter(
                array_map(static fn (string $part): string => trim($part), explode('|', $raw)),
                static fn (string $part): bool => '' !== $part,
            ));
        }

        if (!\is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            if (!\is_string($id) && !\is_int($id)) {
                continue;
            }
            $id = trim((string) $id);
            if (Uuid::isValid($id)) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }
}
