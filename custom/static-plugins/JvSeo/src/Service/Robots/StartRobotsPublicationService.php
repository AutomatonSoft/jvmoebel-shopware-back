<?php declare(strict_types=1);

namespace Jv\Seo\Service\Robots;

use Jv\Seo\Message\RobotsPublicationMessage;
use Jv\Seo\Service\Robots\Exception\RobotsPublicationInProgressException;
use Jv\Seo\Service\Sitemap\EligibleSitemapSalesChannels;
use Jv\Seo\Service\Sitemap\Exception\SitemapExportScopeException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class StartRobotsPublicationService
{
    public function __construct(
        private EligibleSitemapSalesChannels $salesChannels,
        private RobotsTextValidator $validator,
        private RobotsPublicationRunStore $runs,
        private MessageBusInterface $messageBus,
        private LockFactory $lockFactory,
    ) {
    }

    public function execute(string $salesChannelId, string $content, Context $context): string
    {
        if ('' === $salesChannelId) {
            throw new SitemapExportScopeException('A sales channel is required.');
        }
        $this->validator->validate($content);
        $this->salesChannels->resolve($salesChannelId, $context);

        $lock = $this->lockFactory->createLock('jv-seo-robots-start-'.$salesChannelId, 30.0);
        if (!$lock->acquire()) {
            throw new RobotsPublicationInProgressException('A robots publication is already starting for this sales channel.');
        }

        try {
            $latest = $this->runs->getLatestForChannel($salesChannelId, $context);
            if (null !== $latest && in_array($latest->getStatus(), ['pending', 'running'], true)) {
                throw new RobotsPublicationInProgressException('A robots publication is already running for this sales channel.');
            }

            $runId = $this->runs->createPending($salesChannelId, $content, $this->initiator($context), $context);
            try {
                $this->messageBus->dispatch(new RobotsPublicationMessage($runId));
            } catch (\Throwable $exception) {
                $run = $this->runs->get($runId, $context);
                if (null !== $run) {
                    $this->runs->markFailed($run, 'robots_message_dispatch_failed', $context);
                }

                throw $exception;
            }

            return $runId;
        } finally {
            $lock->release();
        }
    }

    private function initiator(Context $context): string
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource && null !== $source->getUserId() ? 'admin:'.$source->getUserId() : 'system';
    }
}
