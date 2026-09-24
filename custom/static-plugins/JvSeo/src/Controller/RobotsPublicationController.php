<?php declare(strict_types=1);

namespace Jv\Seo\Controller;

use Jv\Seo\Core\Content\RobotsPublicationRun\RobotsPublicationRunCollection;
use Jv\Seo\Core\Content\RobotsPublicationRun\RobotsPublicationRunEntity;
use Jv\Seo\Service\Robots\Exception\RobotsPublicationInProgressException;
use Jv\Seo\Service\Robots\Exception\RobotsTextValidationException;
use Jv\Seo\Service\Robots\RobotsPublicationRunStore;
use Jv\Seo\Service\Robots\StartRobotsPublicationService;
use Jv\Seo\Service\Sitemap\EligibleSitemapSalesChannels;
use Jv\Seo\Service\Sitemap\Exception\SitemapExportScopeException;
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
final class RobotsPublicationController extends AbstractController
{
    /** @param EntityRepository<RobotsPublicationRunCollection> $runs */
    public function __construct(
        private readonly StartRobotsPublicationService $start,
        private readonly EligibleSitemapSalesChannels $salesChannels,
        private readonly RobotsPublicationRunStore $runStore,
        private readonly EntityRepository $runs,
    ) {
    }

    #[Route(path: '/api/_action/jv-seo/robots/sales-channels', name: 'api.action.jv_seo.robots.sales_channels', methods: ['GET'], defaults: ['_acl' => ['jv_seo_robots:read']])]
    public function salesChannels(Context $context): JsonResponse
    {
        try {
            $channels = $this->salesChannels->resolve(null, $context);
        } catch (SitemapExportScopeException) {
            return new JsonResponse(['data' => []]);
        }

        $data = [];
        foreach ($channels->getElements() as $channel) {
            $hosts = $this->hosts($channel);
            if ([] === $hosts) {
                continue;
            }
            $data[] = ['id' => $channel->getId(), 'name' => $channel->getName(), 'hosts' => $hosts];
        }

        return new JsonResponse(['data' => $data]);
    }

    #[Route(path: '/api/_action/jv-seo/robots', name: 'api.action.jv_seo.robots.latest', methods: ['GET'], defaults: ['_acl' => ['jv_seo_robots:read']])]
    public function latest(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');
        if (!is_string($salesChannelId) || !Uuid::isValid($salesChannelId)) {
            return $this->error('invalid_sales_channel_id', 'salesChannelId must be a valid ID.', Response::HTTP_BAD_REQUEST);
        }
        try {
            $this->salesChannels->resolve($salesChannelId, $context);
        } catch (SitemapExportScopeException) {
            return $this->error('sales_channel_not_eligible', 'The selected sales channel is not eligible for robots.txt.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $run = $this->runStore->getLatestForChannel($salesChannelId, $context);

        return new JsonResponse(['data' => [
            'content' => $run?->getContent() ?? '',
            'run' => null === $run ? null : $this->serialize($run),
        ]]);
    }

    #[Route(path: '/api/_action/jv-seo/robots/publications', name: 'api.action.jv_seo.robots.publications', methods: ['GET'], defaults: ['_acl' => ['jv_seo_robots:read']])]
    public function publications(Request $request, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->setLimit(min(100, max(1, $request->query->getInt('limit', 25))));
        $criteria->setOffset(max(0, $request->query->getInt('offset', 0)));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $result = $this->runs->search($criteria, $context);

        return new JsonResponse([
            'data' => array_values(array_map(static function (RobotsPublicationRunEntity $run): array {
                return [
                    'salesChannelId' => $run->getSalesChannelId(),
                    'publicationResult' => $run->getPublicationResult(),
                ];
            }, $result->getElements())),
            'total' => $result->getTotal(),
        ]);
    }

    #[Route(path: '/api/_action/jv-seo/robots', name: 'api.action.jv_seo.robots.publish', methods: ['POST'], defaults: ['_acl' => ['jv_seo_robots:write']])]
    public function publish(Request $request, Context $context): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->error('invalid_request', 'Request body must be a JSON object.', Response::HTTP_BAD_REQUEST);
        }
        $salesChannelId = $payload['salesChannelId'] ?? null;
        $content = $payload['content'] ?? null;
        if (!is_string($salesChannelId) || !Uuid::isValid($salesChannelId)) {
            return $this->error('invalid_sales_channel_id', 'salesChannelId must be a valid ID.', Response::HTTP_BAD_REQUEST);
        }
        if (!is_string($content)) {
            return $this->error('invalid_content', 'content must be a string.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $runId = $this->start->execute($salesChannelId, $content, $context);
        } catch (RobotsTextValidationException) {
            return $this->error('invalid_robots_content', 'Robots.txt contains unsupported characters or exceeds 32 KiB.', Response::HTTP_BAD_REQUEST);
        } catch (SitemapExportScopeException) {
            return $this->error('sales_channel_not_eligible', 'The selected sales channel is not eligible for robots.txt.', Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RobotsPublicationInProgressException) {
            return $this->error('robots_publication_in_progress', 'A robots.txt publication is already running for this sales channel.', Response::HTTP_CONFLICT);
        } catch (\Throwable) {
            return $this->error('robots_publication_queue_failed', 'Robots.txt publication could not be queued.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['data' => ['id' => $runId]], Response::HTTP_ACCEPTED);
    }

    #[Route(path: '/api/_action/jv-seo/robots/runs/{runId}', name: 'api.action.jv_seo.robots.run', methods: ['GET'], defaults: ['_acl' => ['jv_seo_robots:read']], requirements: ['runId' => '[0-9a-f]{32}'])]
    public function getRun(string $runId, Context $context): JsonResponse
    {
        if (!Uuid::isValid($runId)) {
            return $this->error('run_not_found', 'Robots.txt publication run was not found.', Response::HTTP_NOT_FOUND);
        }
        $run = $this->runs->search(new Criteria([$runId]), $context)->first();
        if (!$run instanceof RobotsPublicationRunEntity) {
            return $this->error('run_not_found', 'Robots.txt publication run was not found.', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => $this->serialize($run)]);
    }

    /** @return list<array{host: string, languageId: string}> */
    private function hosts(SalesChannelEntity $channel): array
    {
        $hosts = [];
        foreach ($channel->getDomains()?->getElements() ?? [] as $domain) {
            $parts = parse_url($domain->getUrl());
            $host = strtolower(is_array($parts) ? ($parts['host'] ?? '') : '');
            if (!preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $host) || str_contains($host, '..')) {
                continue;
            }
            $hosts[$host] ??= ['host' => $host, 'languageId' => $domain->getLanguageId()];
        }

        return array_values($hosts);
    }

    /** @return array<string, mixed> */
    private function serialize(RobotsPublicationRunEntity $run): array
    {
        return [
            'id' => $run->getId(),
            'salesChannelId' => $run->getSalesChannelId(),
            'content' => $run->getContent(),
            'status' => $run->getStatus(),
            'publicationResult' => $run->getPublicationResult(),
            'safeFailureCode' => $run->getSafeFailureCode(),
            'safeFailureMessage' => $run->getSafeFailureMessage(),
            'createdAt' => $run->getCreatedAt()?->format(DATE_ATOM),
            'startedAt' => $run->getStartedAt()?->format(DATE_ATOM),
            'finishedAt' => $run->getFinishedAt()?->format(DATE_ATOM),
        ];
    }

    private function error(string $code, string $detail, int $status): JsonResponse
    {
        return new JsonResponse(['errors' => [['code' => $code, 'detail' => $detail]]], $status);
    }
}
