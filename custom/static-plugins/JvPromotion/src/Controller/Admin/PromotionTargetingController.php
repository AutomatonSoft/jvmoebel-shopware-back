<?php declare(strict_types=1);

namespace Jv\Promotion\Controller\Admin;

use Jv\Promotion\Service\Promotion\JvPromotionTargetInputParser;
use Jv\Promotion\Service\Query\GetPromotionTargetsService;
use Jv\Promotion\Service\Query\ListPromotionCollectionsService;
use Jv\Promotion\Service\Query\ListPromotionFactoriesService;
use Jv\Promotion\Service\Query\ListPromotionFactoryPrefixesService;
use Jv\Promotion\Service\Query\PreviewPromotionTargetsService;
use Jv\Promotion\Service\Query\SearchPromotionProductsService;
use Jv\Promotion\Service\Write\SyncJvPromotionService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
final class PromotionTargetingController extends AbstractController
{
    public function __construct(
        private readonly ListPromotionFactoriesService $factories,
        private readonly ListPromotionFactoryPrefixesService $factoryPrefixes,
        private readonly ListPromotionCollectionsService $collections,
        private readonly SearchPromotionProductsService $products,
        private readonly PreviewPromotionTargetsService $preview,
        private readonly SyncJvPromotionService $sync,
        private readonly GetPromotionTargetsService $promotionTargets,
        private readonly JvPromotionTargetInputParser $targetParser,
    ) {
    }

    #[Route(path: '/api/_action/jv-promotion/targets', name: 'api.action.jv_promotion.targets', methods: ['GET'], defaults: ['_acl' => ['promotion.viewer', 'jv_promotion_aftercool:read']])]
    public function getTargets(Request $request, Context $context): JsonResponse
    {
        $promotionId = $request->query->getString('promotionId');
        if ('' === $promotionId) {
            return new JsonResponse(['errors' => [['code' => 'invalid_promotion_id', 'detail' => 'promotionId is required.']]], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->promotionTargets->execute($promotionId, $context));
    }

    #[Route(path: '/api/_action/jv-promotion/factories', name: 'api.action.jv_promotion.factories', methods: ['GET'], defaults: ['_acl' => ['promotion.viewer', 'jv_promotion_aftercool:read']])]
    public function listFactories(Request $request): JsonResponse
    {
        $query = $request->query->getString('q');
        $limit = min(1000, max(1, $request->query->getInt('limit', 50)));
        return new JsonResponse([
            'data' => $this->factories->execute('' === $query ? null : $query, $limit),
        ]);
    }

    #[Route(path: '/api/_action/jv-promotion/factory-prefixes', name: 'api.action.jv_promotion.factory_prefixes', methods: ['GET'], defaults: ['_acl' => ['promotion.viewer', 'jv_promotion_aftercool:read']])]
    public function listFactoryPrefixes(Request $request): JsonResponse
    {
        $query = $request->query->getString('q');

        return new JsonResponse([
            'data' => $this->factoryPrefixes->execute('' === $query ? null : $query),
        ]);
    }

    #[Route(path: '/api/_action/jv-promotion/collections', name: 'api.action.jv_promotion.collections', methods: ['GET'], defaults: ['_acl' => ['promotion.viewer', 'jv_promotion_aftercool:read']])]
    public function listCollections(Request $request): JsonResponse
    {
        $factoryId = $request->query->getInt('factoryId');
        if ($factoryId < 1) {
            return new JsonResponse(['errors' => [['code' => 'invalid_factory_id', 'detail' => 'factoryId must be a positive integer.']]], Response::HTTP_BAD_REQUEST);
        }

        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $offset = max(0, $request->query->getInt('offset', 0));
        $query = $request->query->getString('q');

        return new JsonResponse($this->collections->execute(
            $factoryId,
            '' === $query ? null : $query,
            $limit,
            $offset,
        ));
    }

    #[Route(path: '/api/_action/jv-promotion/products', name: 'api.action.jv_promotion.products', methods: ['GET'], defaults: ['_acl' => ['promotion.viewer', 'jv_promotion_aftercool:read']])]
    public function searchProducts(Request $request): JsonResponse
    {
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $offset = max(0, $request->query->getInt('offset', 0));
        $factoryId = $request->query->has('factoryId') ? $request->query->getInt('factoryId') : null;
        $collectionId = $request->query->getString('collectionId');
        $query = $request->query->getString('q');

        $response = new JsonResponse();
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);
        $response->setData($this->products->execute(
            '' === $query ? null : $query,
            (null !== $factoryId && $factoryId > 0) ? $factoryId : null,
            '' === $collectionId ? null : $collectionId,
            $limit,
            $offset,
        ));

        return $response;
    }

    #[Route(path: '/api/_action/jv-promotion/preview', name: 'api.action.jv_promotion.preview', methods: ['POST'], defaults: ['_acl' => ['promotion.editor', 'jv_promotion_aftercool:write']])]
    public function preview(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return new JsonResponse(['errors' => [['code' => 'invalid_json', 'detail' => 'Request body must be JSON.']]], Response::HTTP_BAD_REQUEST);
        }

        $targetsRaw = isset($payload['targets']) && \is_array($payload['targets']) ? $payload['targets'] : [];
        $parsed = $this->targetParser->parse($targetsRaw);

        $limit = min(100, max(1, isset($payload['limit']) ? (int) $payload['limit'] : 25));
        $offset = max(0, isset($payload['offset']) ? (int) $payload['offset'] : 0);

        $response = new JsonResponse();
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);
        $response->setData([
            ...$this->preview->execute($parsed['targets'], $limit, $offset),
            'warnings' => $parsed['warnings'],
        ]);

        return $response;
    }

    #[Route(path: '/api/_action/jv-promotion/sync', name: 'api.action.jv_promotion.sync', methods: ['POST'], defaults: ['_acl' => ['promotion.editor', 'jv_promotion_aftercool:write']])]
    public function sync(Request $request, Context $context): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return new JsonResponse(['errors' => [['code' => 'invalid_json', 'detail' => 'Request body must be JSON.']]], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->sync->execute($payload, $context);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['errors' => [['code' => 'invalid_payload', 'detail' => $exception->getMessage()]]], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($result);
    }
}
