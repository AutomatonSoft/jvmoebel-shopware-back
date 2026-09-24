<?php declare(strict_types=1);

namespace Jv\Seo\MessageHandler;

use Jv\Seo\Message\SitemapExportMessage;
use Jv\Seo\Service\Sitemap\Exception\SitemapPublicationException;
use Jv\Seo\Service\Sitemap\GenerateAndPublishSitemapService;
use Jv\Seo\Service\Sitemap\SitemapExportRunStatus;
use Jv\Seo\Service\Sitemap\SitemapExportRunStore;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SitemapExportMessageHandler
{
    private const RUN_LOCK_TTL_SECONDS = 7200.0;

    public function __construct(private SitemapExportRunStore $runs, private GenerateAndPublishSitemapService $generator, private LockFactory $lockFactory, private LoggerInterface $logger)
    {
    }

    public function __invoke(SitemapExportMessage $message): void
    {
        $lock = $this->lockFactory->createLock('jv-seo-sitemap-run-'.$message->runId, self::RUN_LOCK_TTL_SECONDS);
        if (!$lock->acquire()) {
            return;
        }
        $context = Context::createDefaultContext();
        try {
            $run = $this->runs->get($message->runId, $context);
            if (null === $run) {
                return;
            }
            if (SitemapExportRunStatus::tryFrom($run->getStatus())?->isTerminal()) {
                $this->removeSnapshotsSafely($message->runId);

                return;
            }
            if (!$this->runs->markRunning($run, $context)) {
                return;
            }
            $results = $this->generator->execute($run, $context);
            $this->runs->markPublished($run, $results, $context);
            $this->removeSnapshotsSafely($message->runId);
            $this->logger->info('Sitemap export published.', ['operation' => 'sitemap_export_publish', 'runId' => $run->getId(), 'salesChannelId' => $run->getSalesChannelId(), 'publicationCount' => count($results)]);
        } catch (\Throwable $exception) {
            $safeCode = $exception instanceof SitemapPublicationException ? $exception->safeCode() : 'sitemap_export_failed';
            $run ??= $this->runs->get($message->runId, $context);
            if (null !== $run) {
                $this->runs->markFailed($run, $safeCode, $context);
                $this->removeSnapshotsSafely($message->runId);
            }
            $this->logger->error('Sitemap export failed.', ['operation' => 'sitemap_export_publish', 'runId' => $message->runId, 'safeCode' => $safeCode, 'retryable' => $exception instanceof SitemapPublicationException && $exception->isRetryable()]);
        } finally {
            $lock->release();
        }
    }

    private function removeSnapshotsSafely(string $runId): void
    {
        try {
            $this->generator->removeSnapshots($runId);
        } catch (\Throwable $exception) {
            $this->logger->warning('Sitemap publication snapshots could not be removed.', ['operation' => 'sitemap_snapshot_cleanup', 'runId' => $runId, 'exceptionClass' => $exception::class]);
        }
    }
}
