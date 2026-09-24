<?php declare(strict_types=1);

namespace Jv\Seo\MessageHandler;

use Jv\Seo\Message\RobotsPublicationMessage;
use Jv\Seo\Service\Robots\Exception\RobotsPublicationException;
use Jv\Seo\Service\Robots\GenerateAndPublishRobotsService;
use Jv\Seo\Service\Robots\RobotsPublicationRunStatus;
use Jv\Seo\Service\Robots\RobotsPublicationRunStore;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RobotsPublicationMessageHandler
{
    private const RUN_LOCK_TTL_SECONDS = 900.0;

    public function __construct(
        private RobotsPublicationRunStore $runs,
        private GenerateAndPublishRobotsService $publisher,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RobotsPublicationMessage $message): void
    {
        $lock = $this->lockFactory->createLock('jv-seo-robots-run-'.$message->runId, self::RUN_LOCK_TTL_SECONDS);
        if (!$lock->acquire()) {
            return;
        }

        $context = Context::createDefaultContext();
        $run = null;
        try {
            $run = $this->runs->get($message->runId, $context);
            if (null === $run || RobotsPublicationRunStatus::tryFrom($run->getStatus())?->isTerminal()) {
                return;
            }
            if (!$this->runs->markRunning($run, $context)) {
                return;
            }

            $results = $this->publisher->execute($run, $context);
            $this->runs->markPublished($run, $results, $context);
            $this->removeSnapshotsSafely($message->runId, $context);
            $this->logger->info('Robots.txt published.', [
                'operation' => 'robots_publish',
                'runId' => $run->getId(),
                'salesChannelId' => $run->getSalesChannelId(),
                'publicationCount' => count($results),
            ]);
        } catch (\Throwable $exception) {
            $safeCode = $exception instanceof RobotsPublicationException ? $exception->safeCode() : 'robots_publication_failed';
            $run ??= $this->runs->get($message->runId, $context);
            if (null !== $run) {
                $this->runs->markFailed($run, $safeCode, $context);
                $this->removeSnapshotsSafely($message->runId, $context);
            }
            $this->logger->error('Robots.txt publication failed.', [
                'operation' => 'robots_publish',
                'runId' => $message->runId,
                'safeCode' => $safeCode,
                'retryable' => $exception instanceof RobotsPublicationException && $exception->isRetryable(),
            ]);
        } finally {
            $lock->release();
        }
    }

    private function removeSnapshotsSafely(string $runId, Context $context): void
    {
        try {
            $this->publisher->removeSnapshots($runId, $context);
        } catch (\Throwable $exception) {
            $this->logger->warning('Robots publication snapshots could not be removed.', [
                'operation' => 'robots_snapshot_cleanup',
                'runId' => $runId,
                'exceptionClass' => $exception::class,
            ]);
        }
    }
}
