<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap;

use Jv\Seo\Message\SitemapExportMessage;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class StartSitemapExportService
{
    public function __construct(private EligibleSitemapSalesChannels $salesChannels, private SitemapExportRunStore $runs, private MessageBusInterface $messageBus)
    {
    }

    public function execute(?string $salesChannelId, Context $context): string
    {
        $this->salesChannels->resolve($salesChannelId, $context);
        $runId = $this->runs->createPending($salesChannelId, $this->initiator($context), $context);
        try {
            $this->messageBus->dispatch(new SitemapExportMessage($runId));
        } catch (\Throwable $exception) {
            $run = $this->runs->get($runId, $context);
            if (null !== $run) {
                $this->runs->markFailed($run, 'sitemap_message_dispatch_failed', $context);
            } throw $exception;
        }

        return $runId;
    }

    private function initiator(Context $context): string
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource && null !== $source->getUserId() ? 'admin:'.$source->getUserId() : 'system';
    }
}
