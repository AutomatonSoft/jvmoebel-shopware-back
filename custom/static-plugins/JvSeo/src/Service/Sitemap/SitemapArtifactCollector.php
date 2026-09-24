<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap;

use Jv\Seo\Service\Sitemap\Dto\SitemapArtifact;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Sitemap\Service\SitemapListerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class SitemapArtifactCollector
{
    public function __construct(
        private SitemapListerInterface $sitemapLister,
        private FilesystemOperator $sitemapFilesystem,
    ) {
    }

    /**
     * @param array<string, string> $publicationIdsByDomain keyed by sales channel domain ID
     *
     * @return array<string, list<SitemapArtifact>> keyed by sales channel domain ID
     */
    public function collect(string $runId, SalesChannelContext $context, array $publicationIdsByDomain): array
    {
        if (!Uuid::isValid($runId)) {
            throw new \InvalidArgumentException('Sitemap snapshot run ID is invalid.');
        }

        $artifacts = [];
        foreach ($publicationIdsByDomain as $domainId => $publicationId) {
            if (!Uuid::isValid($domainId) || !Uuid::isValid($publicationId)) {
                throw new \InvalidArgumentException('Sitemap snapshot publication scope is invalid.');
            }
            $artifacts[$domainId] = [];
        }

        try {
            foreach ($this->sitemapLister->getSitemaps($context) as $sitemap) {
                $sourcePath = ltrim((string) parse_url($sitemap->getFilename(), PHP_URL_PATH), '/');
                $filename = basename($sourcePath);
                if ('' === $sourcePath || $filename !== $sourcePath && !str_starts_with($sourcePath, 'sitemap/')) {
                    continue;
                }

                foreach ($publicationIdsByDomain as $domainId => $publicationId) {
                    if (!str_contains($filename, '-'.$domainId.'-sitemap-')) {
                        continue;
                    }

                    $artifacts[$domainId][] = $this->copyToSnapshot($runId, $publicationId, $sourcePath, $filename);
                }
            }
        } catch (\Throwable $exception) {
            $this->removeRunSnapshots($runId);

            throw $exception;
        }

        return array_filter($artifacts, static fn (array $domainArtifacts): bool => [] !== $domainArtifacts);
    }

    public function removeRunSnapshots(string $runId): void
    {
        if (!Uuid::isValid($runId)) {
            return;
        }

        $directory = $this->runDirectory($runId);
        if ($this->sitemapFilesystem->directoryExists($directory)) {
            $this->sitemapFilesystem->deleteDirectory($directory);
        }
    }

    private function copyToSnapshot(string $runId, string $publicationId, string $sourcePath, string $filename): SitemapArtifact
    {
        $snapshotPath = $this->runDirectory($runId).'/'.$publicationId.'/'.$filename;
        $this->sitemapFilesystem->copy($sourcePath, $snapshotPath);

        $stream = $this->sitemapFilesystem->readStream($snapshotPath);
        if (!is_resource($stream)) {
            throw new \RuntimeException('Generated sitemap snapshot could not be read.');
        }

        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            $checksum = hash_final($hash);
        } finally {
            fclose($stream);
        }

        return new SitemapArtifact($filename, $snapshotPath, 'application/gzip', $checksum, $this->sitemapFilesystem->fileSize($snapshotPath));
    }

    private function runDirectory(string $runId): string
    {
        return 'jv-seo-publications/'.$runId;
    }
}
