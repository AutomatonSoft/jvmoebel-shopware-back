<?php declare(strict_types=1);

namespace Jv\Seo\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class SitemapExportMessage implements AsyncMessageInterface
{
    public function __construct(public string $runId)
    {
        if (!Uuid::isValid($this->runId)) {
            throw new \InvalidArgumentException('Sitemap export message has an invalid run ID.');
        }
    }
}
