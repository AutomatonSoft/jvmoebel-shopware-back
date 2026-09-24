<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

final readonly class SitemapGenerationLock
{
    private const TTL_SECONDS = 7200.0;

    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function create(string $salesChannelId, string $languageId): LockInterface
    {
        return $this->lockFactory->createLock('jv-seo-sitemap-'.$salesChannelId.'-'.$languageId, self::TTL_SECONDS);
    }
}
