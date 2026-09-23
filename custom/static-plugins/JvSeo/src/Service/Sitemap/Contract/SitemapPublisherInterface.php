<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap\Contract;

use Jv\Seo\Service\Sitemap\Dto\PublicationResult;
use Jv\Seo\Service\Sitemap\Dto\SitemapExport;

interface SitemapPublisherInterface
{
    public function publish(SitemapExport $export): PublicationResult;
}
