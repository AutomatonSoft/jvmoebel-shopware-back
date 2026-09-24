<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap;

use Jv\Seo\Core\Content\SitemapExportRun\SitemapExportRunEntity;
use Jv\Seo\Service\Sitemap\Contract\SitemapPublisherInterface;
use Jv\Seo\Service\Sitemap\Dto\SitemapExport;
use Jv\Seo\Service\Sitemap\Exception\SitemapPublicationException;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

final readonly class GenerateAndPublishSitemapService
{
    public function __construct(
        private EligibleSitemapSalesChannels $salesChannels,
        private AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private SitemapExporterInterface $sitemapExporter,
        private SitemapArtifactCollector $artifactCollector,
        private SitemapPublisherInterface $publisher,
        private SitemapGenerationLock $generationLock,
        private SitemapExportRunStore $runs,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<array{publicationId: string, destinationVersion: string, publishedAt: string, publishedPaths: list<string>, host: string, publicSitemapUrl: string, artifactCount: int, salesChannelId: string, languageId: string}> */
    public function execute(SitemapExportRunEntity $run, Context $context): array
    {
        $publicationResults = $this->existingPublicationResults($run);
        try {
            $exports = $this->publicationPlan($run, $context);
            foreach ($exports as $export) {
                if (isset($publicationResults[$export->publicationId])) {
                    continue;
                }

                $result = $this->publisher->publish($export);
                $publicationResult = [
                    ...$result->toArray(),
                    'host' => $export->host,
                    'publicSitemapUrl' => 'https://'.$export->host.'/sitemap.xml',
                    'artifactCount' => count($export->artifacts),
                    'salesChannelId' => $export->salesChannelId,
                    'languageId' => $export->languageId,
                ];
                $this->runs->appendPublicationResult($run->getId(), $publicationResult, $context);
                $publicationResults[$export->publicationId] = $publicationResult;
            }
        } catch (SitemapPublicationException $exception) {
            throw new SitemapPublicationException($exception->safeCode(), $exception->isRetryable(), $exception, array_values($publicationResults));
        } catch (\Throwable $exception) {
            throw new SitemapPublicationException('sitemap_export_failed', false, $exception, array_values($publicationResults));
        }

        return array_values($publicationResults);
    }

    public function removeSnapshots(string $runId): void
    {
        $this->artifactCollector->removeRunSnapshots($runId);
    }

    /** @return list<SitemapExport> */
    private function publicationPlan(SitemapExportRunEntity $run, Context $context): array
    {
        $storedPlan = $run->getPublicationPlan();
        if (null !== $storedPlan) {
            return $this->hydratePublicationPlan($storedPlan);
        }

        return $this->preparePublicationPlan($run, $context);
    }

    /** @return list<SitemapExport> */
    private function preparePublicationPlan(SitemapExportRunEntity $run, Context $context): array
    {
        $this->artifactCollector->removeRunSnapshots($run->getId());
        $exports = [];
        $generatedAt = $this->clock->now();

        try {
            foreach ($this->salesChannels->resolve($run->getSalesChannelId(), $context) as $salesChannel) {
                $domainsByLanguage = [];
                foreach ($salesChannel->getDomains() ?? [] as $domain) {
                    $domainsByLanguage[$domain->getLanguageId()][$domain->getId()] = $domain;
                }

                foreach ($domainsByLanguage as $languageId => $domains) {
                    $publicationIdsByDomain = [];
                    foreach (array_keys($domains) as $domainId) {
                        $publicationIdsByDomain[$domainId] = $this->publicationId($run->getId(), $salesChannel->getId(), $languageId, $domainId);
                    }

                    $lock = $this->generationLock->create($salesChannel->getId(), $languageId);
                    if (!$lock->acquire()) {
                        throw new SitemapPublicationException('sitemap_scope_locked', false);
                    }

                    try {
                        $salesChannelContext = $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $languageId]);
                        $this->generate($salesChannelContext);
                        $artifactsByDomain = $this->artifactCollector->collect($run->getId(), $salesChannelContext, $publicationIdsByDomain);
                    } finally {
                        $lock->release();
                    }

                    foreach ($domains as $domainId => $domain) {
                        $artifacts = $artifactsByDomain[$domainId] ?? [];
                        if ([] === $artifacts) {
                            throw new SitemapPublicationException('sitemap_artifacts_missing', false);
                        }
                        $exports[] = new SitemapExport($publicationIdsByDomain[$domainId], $salesChannel->getId(), $languageId, $this->host($domain->getUrl()), $generatedAt, $artifacts);
                    }
                }
            }

            $this->runs->savePublicationPlan($run->getId(), $exports, $context);
        } catch (\Throwable $exception) {
            $this->artifactCollector->removeRunSnapshots($run->getId());

            throw $exception;
        }

        return $exports;
    }

    private function generate(\Shopware\Core\System\SalesChannel\SalesChannelContext $context): void
    {
        $result = $this->sitemapExporter->generate($context);
        while (!$result->isFinish()) {
            $result = $this->sitemapExporter->generate($context, false, $result->getProvider(), $result->getOffset());
        }
    }

    private function publicationId(string $runId, string $salesChannelId, string $languageId, string $domainId): string
    {
        return Uuid::fromStringToHex($runId.'|'.$salesChannelId.'|'.$languageId.'|'.$domainId);
    }

    private function host(string $domainUrl): string
    {
        $host = parse_url($domainUrl, PHP_URL_HOST);
        if (!is_string($host) || '' === $host) {
            throw new SitemapPublicationException('sitemap_domain_invalid', false);
        }

        return strtolower($host);
    }

    /** @param array<string, mixed> $storedPlan
     *
     * @return list<SitemapExport>
     */
    private function hydratePublicationPlan(array $storedPlan): array
    {
        $publications = $storedPlan['publications'] ?? null;
        if (!is_array($publications) || [] === $publications) {
            throw new SitemapPublicationException('sitemap_publication_plan_invalid', false);
        }

        try {
            return array_values(array_map(static function (mixed $publication): SitemapExport {
                if (!is_array($publication)) {
                    throw new \InvalidArgumentException('Persisted sitemap publication is invalid.');
                }

                return SitemapExport::fromArray($publication);
            }, $publications));
        } catch (\InvalidArgumentException $exception) {
            throw new SitemapPublicationException('sitemap_publication_plan_invalid', false, $exception);
        }
    }

    /** @return array<string, array{publicationId: string, destinationVersion: string, publishedAt: string, publishedPaths: list<string>, host: string, publicSitemapUrl: string, artifactCount: int, salesChannelId: string, languageId: string}> */
    private function existingPublicationResults(SitemapExportRunEntity $run): array
    {
        $results = [];
        foreach ($run->getPublicationResult()['publications'] ?? [] as $publication) {
            if (!is_array($publication) || !is_string($publication['publicationId'] ?? null)) {
                continue;
            }
            /* @var array{publicationId: string, destinationVersion: string, publishedAt: string, publishedPaths: list<string>, host: string, publicSitemapUrl: string, artifactCount: int, salesChannelId: string, languageId: string} $publication */
            $results[$publication['publicationId']] = $publication;
        }

        return $results;
    }
}
