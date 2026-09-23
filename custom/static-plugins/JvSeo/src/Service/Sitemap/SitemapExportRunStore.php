<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap;

use Jv\Seo\Core\Content\SitemapExportRun\SitemapExportRunCollection;
use Jv\Seo\Core\Content\SitemapExportRun\SitemapExportRunEntity;
use Jv\Seo\Service\Sitemap\Dto\SitemapExport;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class SitemapExportRunStore
{
    /** @param EntityRepository<SitemapExportRunCollection> $repository */
    public function __construct(private EntityRepository $repository)
    {
    }

    public function createPending(?string $salesChannelId, string $initiator, Context $context): string
    {
        $id = Uuid::randomHex();
        $this->repository->create([['id' => $id, 'initiator' => $initiator, 'salesChannelId' => $salesChannelId, 'status' => SitemapExportRunStatus::Pending->value, 'scope' => ['allEligibleSalesChannels' => null === $salesChannelId]]], $context);

        return $id;
    }

    public function get(string $id, Context $context): ?SitemapExportRunEntity
    {
        $run = $this->repository->search(new Criteria([$id]), $context)->first();

        return $run instanceof SitemapExportRunEntity ? $run : null;
    }

    public function markRunning(SitemapExportRunEntity $run, Context $context): bool
    {
        if (SitemapExportRunStatus::tryFrom($run->getStatus())?->isTerminal()) {
            return false;
        }
        $this->repository->update([['id' => $run->getId(), 'status' => SitemapExportRunStatus::Running->value, 'startedAt' => $run->getStartedAt() ?? new \DateTimeImmutable(), 'safeFailureCode' => null, 'safeFailureMessage' => null]], $context);

        return true;
    }

    /** @param list<SitemapExport> $exports */
    public function savePublicationPlan(string $runId, array $exports, Context $context): void
    {
        $this->repository->update([[
            'id' => $runId,
            'publicationPlan' => ['publications' => array_map(static fn (SitemapExport $export): array => $export->toArray(), $exports)],
        ]], $context);
    }

    /**
     * @param array{publicationId: string, destinationVersion: string, publishedAt: string, publishedPaths: list<string>, host: string, publicSitemapUrl: string, artifactCount: int, salesChannelId: string, languageId: string} $publicationResult
     *
     * @return list<array<string, mixed>>
     */
    public function appendPublicationResult(string $runId, array $publicationResult, Context $context): array
    {
        $run = $this->get($runId, $context);
        if (!$run instanceof SitemapExportRunEntity) {
            throw new \RuntimeException('Sitemap export run was not found while recording a publication result.');
        }

        $publications = $run->getPublicationResult()['publications'] ?? [];
        if (!is_array($publications)) {
            $publications = [];
        }

        $merged = [];
        $wasReplaced = false;
        foreach ($publications as $publication) {
            if (!is_array($publication)) {
                continue;
            }
            if (($publication['publicationId'] ?? null) === $publicationResult['publicationId']) {
                $merged[] = $publicationResult;
                $wasReplaced = true;

                continue;
            }
            $merged[] = $publication;
        }
        if (!$wasReplaced) {
            $merged[] = $publicationResult;
        }

        $this->repository->update([['id' => $runId, 'publicationResult' => ['publications' => $merged]]], $context);

        return $merged;
    }

    /** @param list<array<string, mixed>> $publicationResults */
    public function markPublished(SitemapExportRunEntity $run, array $publicationResults, Context $context): void
    {
        $singlePublicationId = 1 === count($publicationResults) ? ($publicationResults[0]['publicationId'] ?? null) : null;
        $this->repository->update([['id' => $run->getId(), 'status' => SitemapExportRunStatus::Published->value, 'publicationId' => is_string($singlePublicationId) ? $singlePublicationId : null, 'publicationResult' => ['publications' => $publicationResults], 'finishedAt' => new \DateTimeImmutable(), 'safeFailureCode' => null, 'safeFailureMessage' => null]], $context);
    }

    public function markFailed(SitemapExportRunEntity $run, string $safeCode, Context $context): void
    {
        $this->repository->update([['id' => $run->getId(), 'status' => SitemapExportRunStatus::Failed->value, 'finishedAt' => new \DateTimeImmutable(), 'safeFailureCode' => $safeCode, 'safeFailureMessage' => 'Sitemap generation and publication could not be completed.']], $context);
    }
}
