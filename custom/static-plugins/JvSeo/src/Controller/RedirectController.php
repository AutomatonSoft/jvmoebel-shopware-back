<?php declare(strict_types=1);

namespace Jv\Seo\Controller;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Jv\Seo\Service\Redirect\Exception\RedirectValidationException;
use Jv\Seo\Service\Redirect\RedirectQueryService;
use Jv\Seo\Service\Redirect\SaveRedirectService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
final class RedirectController extends AbstractController
{
    public function __construct(
        private readonly RedirectQueryService $query,
        private readonly SaveRedirectService $save,
    ) {
    }

    #[Route(path: '/api/_action/jv-seo/redirects', name: 'api.action.jv_seo.redirect.list', methods: ['GET'], defaults: ['_acl' => ['product.viewer']])]
    public function list(Request $request, Context $context): JsonResponse
    {
        $type = $request->query->getString('type');
        $productId = $request->query->getString('productId');

        return new JsonResponse($this->query->list(
            '' === $type || 'all' === $type ? null : $type,
            $request->query->getString('term'),
            '' === $productId ? null : $productId,
            max(1, $request->query->getInt('page', 1)),
            min(100, max(1, $request->query->getInt('limit', 25))),
            $context,
        ));
    }

    #[Route(path: '/api/_action/jv-seo/redirects/{id}', name: 'api.action.jv_seo.redirect.detail', methods: ['GET'], defaults: ['_acl' => ['product.viewer']])]
    public function detail(string $id, Context $context): JsonResponse
    {
        $data = $this->query->detail($id, $context);

        return null === $data
            ? new JsonResponse(['errors' => [['code' => 'redirect_not_found', 'detail' => 'Redirect was not found.']]], Response::HTTP_NOT_FOUND)
            : new JsonResponse(['data' => $data]);
    }

    #[Route(path: '/api/_action/jv-seo/redirects', name: 'api.action.jv_seo.redirect.create', methods: ['POST'], defaults: ['_acl' => ['product.editor']])]
    public function create(Request $request, Context $context): JsonResponse
    {
        try {
            $id = $this->save->create($this->payload($request), $context);

            return new JsonResponse(['data' => $this->query->detail($id, $context)], Response::HTTP_CREATED);
        } catch (RedirectValidationException $exception) {
            return $this->validationError($exception);
        } catch (UniqueConstraintViolationException) {
            return $this->conflictError();
        }
    }

    #[Route(path: '/api/_action/jv-seo/redirects/{id}', name: 'api.action.jv_seo.redirect.update', methods: ['PUT'], defaults: ['_acl' => ['product.editor']])]
    public function update(string $id, Request $request, Context $context): JsonResponse
    {
        try {
            $id = $this->save->update($id, $this->payload($request), $context);

            return new JsonResponse(['data' => $this->query->detail($id, $context)]);
        } catch (RedirectValidationException $exception) {
            return $this->validationError($exception);
        } catch (UniqueConstraintViolationException) {
            return $this->conflictError();
        }
    }

    #[Route(path: '/api/_action/jv-seo/sales-channels', name: 'api.action.jv_seo.sales_channel.list', methods: ['GET'], defaults: ['_acl' => ['product.viewer']])]
    public function salesChannels(Context $context): JsonResponse
    {
        return new JsonResponse(['data' => $this->query->salesChannels($context)]);
    }

    #[Route(path: '/api/_action/jv-seo/products/{productId}/targets', name: 'api.action.jv_seo.product.targets', methods: ['GET'], defaults: ['_acl' => ['product.viewer']])]
    public function productTargets(string $productId, Context $context): JsonResponse
    {
        return new JsonResponse(['data' => $this->query->productTargets($productId, $context)]);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RedirectValidationException([['field' => 'request', 'message' => 'Request body must be valid JSON.']]);
        }

        if (!is_array($payload)) {
            throw new RedirectValidationException([['field' => 'request', 'message' => 'Request body must be a JSON object.']]);
        }

        return $payload;
    }

    private function validationError(RedirectValidationException $exception): JsonResponse
    {
        return new JsonResponse(['errors' => array_map(static fn (array $violation): array => [
            'code' => 'invalid_redirect',
            'detail' => $violation['message'],
            'source' => ['pointer' => $violation['field']],
        ], $exception->violations())], Response::HTTP_BAD_REQUEST);
    }

    private function conflictError(): JsonResponse
    {
        return new JsonResponse(['errors' => [[
            'code' => 'redirect_conflict',
            'detail' => 'The redirect conflicts with an existing source URL or product redirect.',
        ]]], Response::HTTP_CONFLICT);
    }
}
