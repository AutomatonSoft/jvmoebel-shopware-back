<?php declare(strict_types=1);

namespace Jv\Seo\Service\Robots;

use Jv\Seo\Core\Content\RobotsPublicationRun\RobotsPublicationRunEntity;
use Jv\Seo\Service\Robots\Contract\RobotsPublisherInterface;
use Jv\Seo\Service\Robots\Dto\RobotsPublication;
use Jv\Seo\Service\Robots\Exception\RobotsPublicationException;
use Jv\Seo\Service\Robots\Exception\RobotsTextValidationException;
use Jv\Seo\Service\Sitemap\EligibleSitemapSalesChannels;
use Jv\Seo\Service\Sitemap\Exception\SitemapExportScopeException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final readonly class GenerateAndPublishRobotsService
{
    public function __construct(
        private EligibleSitemapSalesChannels $salesChannels,
        private FilesystemOperator $sitemapFilesystem,
        private RobotsPublicationRunStore $runs,
        private RobotsPublisherInterface $publisher,
        private RobotsTextValidator $validator,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function execute(RobotsPublicationRunEntity $run, Context $context): array
    {
        $plan = $this->loadPlan($run);
        if ([] === $plan) {
            $plan = $this->createPlan($run, $context);
            $this->runs->savePublicationPlan($run->getId(), $plan, $context);
        }

        $results = $run->getPublicationResult()['publications'] ?? [];
        if (!is_array($results)) {
            $results = [];
        }
        $publishedIds = [];
        foreach ($results as $result) {
            if (is_array($result) && is_string($result['publicationId'] ?? null)) {
                $publishedIds[$result['publicationId']] = true;
            }
        }

        foreach ($plan as $publication) {
            if (isset($publishedIds[$publication->publicationId])) {
                continue;
            }

            $result = $this->publisher->publish($publication);
            $entry = [
                ...$result->toArray(),
                'host' => $publication->host,
                'salesChannelId' => $publication->salesChannelId,
                'languageId' => $publication->languageId,
                'publicRobotsUrl' => 'https://'.$publication->host.'/robots.txt',
            ];
            $results = $this->runs->appendPublicationResult($run->getId(), $entry, $context);
            $publishedIds[$publication->publicationId] = true;
        }

        return $results;
    }

    public function removeSnapshots(string $runId, Context $context): void
    {
        $run = $this->runs->get($runId, $context);
        if (null === $run) {
            return;
        }
        foreach ($this->loadPlan($run) as $publication) {
            try {
                if ($this->sitemapFilesystem->fileExists($publication->sourcePath)) {
                    $this->sitemapFilesystem->delete($publication->sourcePath);
                }
            } catch (FilesystemException $exception) {
                throw new RobotsPublicationException('robots_snapshot_cleanup_failed', false, $exception);
            }
        }
    }

    /** @return list<RobotsPublication> */
    private function createPlan(RobotsPublicationRunEntity $run, Context $context): array
    {
        $channels = $this->salesChannels->resolve($run->getSalesChannelId(), $context);
        $channel = $channels->first();
        if (!$channel instanceof SalesChannelEntity) {
            throw new SitemapExportScopeException('The selected sales channel is no longer eligible.');
        }

        $domainByHost = [];
        foreach ($channel->getDomains()?->getElements() ?? [] as $domain) {
            $parts = parse_url($domain->getUrl());
            $host = strtolower(is_array($parts) ? ($parts['host'] ?? '') : '');
            if (!preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $host) || str_contains($host, '..')) {
                continue;
            }
            $domainByHost[$host] ??= $domain;
        }
        if ([] === $domainByHost) {
            throw new SitemapExportScopeException('The selected sales channel has no valid host.');
        }

        $content = $run->getContent();
        try {
            $this->validator->validate($content);
        } catch (RobotsTextValidationException $exception) {
            throw new RobotsPublicationException('robots_stored_content_invalid', false, $exception);
        }
        $generatedAt = $this->clock->now();
        $publications = [];
        foreach ($domainByHost as $host => $domain) {
            $publicationId = Uuid::randomHex();
            $sourcePath = 'jv-seo-robots/'.$run->getId().'/'.$publicationId.'/robots.txt';
            try {
                $this->sitemapFilesystem->write($sourcePath, $content);
            } catch (FilesystemException $exception) {
                throw new RobotsPublicationException('robots_snapshot_write_failed', false, $exception);
            }
            $publications[] = new RobotsPublication(
                $publicationId,
                $channel->getId(),
                $domain->getLanguageId(),
                $host,
                \DateTimeImmutable::createFromInterface($generatedAt),
                $sourcePath,
                hash('sha256', $content),
                strlen($content),
            );
        }

        return $publications;
    }

    /** @return list<RobotsPublication> */
    private function loadPlan(RobotsPublicationRunEntity $run): array
    {
        $plan = $run->getPublicationPlan()['publications'] ?? [];
        if (!is_array($plan)) {
            throw new \InvalidArgumentException('Persisted robots publication plan is invalid.');
        }

        return array_values(array_map(static function (mixed $publication): RobotsPublication {
            if (!is_array($publication)) {
                throw new \InvalidArgumentException('Persisted robots publication is invalid.');
            }

            return RobotsPublication::fromArray($publication);
        }, $plan));
    }
}
