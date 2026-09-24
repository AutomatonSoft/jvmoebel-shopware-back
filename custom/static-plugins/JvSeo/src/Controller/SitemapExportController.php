<?php declare(strict_types=1);

namespace Jv\Seo\Controller;

use Jv\Seo\Core\Content\SitemapExportRun\SitemapExportRunCollection;
use Jv\Seo\Core\Content\SitemapExportRun\SitemapExportRunEntity;
use Jv\Seo\Service\Sitemap\EligibleSitemapSalesChannels;
use Jv\Seo\Service\Sitemap\Exception\SitemapExportScopeException;
use Jv\Seo\Service\Sitemap\StartSitemapExportService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
final class SitemapExportController extends AbstractController
{
    /** @param EntityRepository<SitemapExportRunCollection> $runs */
    public function __construct(private readonly StartSitemapExportService $start, private readonly EligibleSitemapSalesChannels $salesChannels, private readonly EntityRepository $runs)
    {
    }

    #[Route(path: '/api/_action/jv-seo/sitemap-exports', name: 'api.action.jv_seo.sitemap_export.create', methods: ['POST'], defaults: ['_acl' => ['jv_seo_sitemap_export:write']])]
    public function create(Request $request, Context $context): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->error('invalid_request', 'Request body must be a JSON object.', Response::HTTP_BAD_REQUEST);
        }
        $salesChannelId = $payload['salesChannelId'] ?? null;
        if (null !== $salesChannelId && (!is_string($salesChannelId) || !Uuid::isValid($salesChannelId))) {
            return $this->error('invalid_sales_channel_id', 'salesChannelId must be a valid ID or null.', Response::HTTP_BAD_REQUEST);
        }
        try {
            $runId = $this->start->execute($salesChannelId, $context);
        } catch (SitemapExportScopeException) {
            return $this->error('sitemap_scope_not_eligible', 'The selected sales channel is not eligible for sitemap export.', Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable) {
            return $this->error('sitemap_queue_failed', 'Sitemap export could not be queued.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['data' => ['id' => $runId]], Response::HTTP_ACCEPTED);
    }

    #[Route(path: '/api/_action/jv-seo/sitemap-exports/{runId}', name: 'api.action.jv_seo.sitemap_export.get', methods: ['GET'], defaults: ['_acl' => ['jv_seo_sitemap_export:read']], requirements: ['runId' => '[0-9a-f]{32}'])]
    public function get(string $runId, Context $context): JsonResponse
    {
        if (!Uuid::isValid($runId)) {
            return $this->error('run_not_found', 'Sitemap export run was not found.', Response::HTTP_NOT_FOUND);
        }
        $run = $this->runs->search(new Criteria([$runId]), $context)->first();
        if (!$run instanceof SitemapExportRunEntity) {
            return $this->error('run_not_found', 'Sitemap export run was not found.', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => $this->serialize($run)]);
    }

    #[Route(path: '/api/_action/jv-seo/sitemap-exports', name: 'api.action.jv_seo.sitemap_export.list', methods: ['GET'], defaults: ['_acl' => ['jv_seo_sitemap_export:read']])]
    public function list(Request $request, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->setLimit(min(100, max(1, $request->query->getInt('limit', 25))));
        $criteria->setOffset(max(0, $request->query->getInt('offset', 0)));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $result = $this->runs->search($criteria, $context);

        return new JsonResponse(['data' => array_values(array_map(fn (SitemapExportRunEntity $run): array => $this->serialize($run), $result->getElements())), 'total' => $result->getTotal()]);
    }

    #[Route(path: '/api/_action/jv-seo/sitemap-exports/sales-channels', name: 'api.action.jv_seo.sitemap_export.sales_channels', methods: ['GET'], defaults: ['_acl' => ['jv_seo_sitemap_export:read']])]
    public function salesChannels(Context $context): JsonResponse
    {
        try {
            $channels = $this->salesChannels->resolve(null, $context);
        } catch (SitemapExportScopeException) {
            return new JsonResponse(['data' => []]);
        }

        return new JsonResponse(['data' => array_values(array_map(static fn (SalesChannelEntity $channel): array => ['id' => $channel->getId(), 'name' => $channel->getName()], $channels->getElements()))]);
    }

    /** @return array<string,mixed> */
    private function serialize(SitemapExportRunEntity $run): array
    {
        return ['id' => $run->getId(), 'initiator' => $run->getInitiator(), 'salesChannelId' => $run->getSalesChannelId(), 'scope' => $run->getScope(), 'status' => $run->getStatus(), 'publicationId' => $run->getPublicationId(), 'publicationResult' => $run->getPublicationResult(), 'safeFailureCode' => $run->getSafeFailureCode(), 'safeFailureMessage' => $run->getSafeFailureMessage(), 'createdAt' => $run->getCreatedAt()?->format(DATE_ATOM), 'startedAt' => $run->getStartedAt()?->format(DATE_ATOM), 'finishedAt' => $run->getFinishedAt()?->format(DATE_ATOM)];
    }

    private function error(string $code, string $detail, int $status): JsonResponse
    {
        return new JsonResponse(['errors' => [['code' => $code, 'detail' => $detail]]], $status);
    }
}
