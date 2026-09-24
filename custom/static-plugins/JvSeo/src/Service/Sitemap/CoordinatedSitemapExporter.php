<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap;

use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Content\Sitemap\SitemapException;
use Shopware\Core\Content\Sitemap\Struct\SitemapGenerationResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class CoordinatedSitemapExporter implements SitemapExporterInterface
{
    public function __construct(private SitemapExporterInterface $inner, private SitemapGenerationLock $generationLock)
    {
    }

    public function generate(SalesChannelContext $context, bool $force = false, ?string $lastProvider = null, ?int $offset = null): SitemapGenerationResult
    {
        $lock = $this->generationLock->create($context->getSalesChannelId(), $context->getLanguageId());
        if (!$lock->acquire()) {
            throw SitemapException::sitemapAlreadyLocked($context);
        }

        try {
            return $this->inner->generate($context, $force, $lastProvider, $offset);
        } finally {
            $lock->release();
        }
    }
}
