<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap;

enum SitemapExportRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Published = 'published';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return self::Published === $this || self::Failed === $this;
    }
}
