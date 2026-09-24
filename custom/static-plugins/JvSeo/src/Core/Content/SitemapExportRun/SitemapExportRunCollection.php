<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\SitemapExportRun;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<SitemapExportRunEntity> */
final class SitemapExportRunCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SitemapExportRunEntity::class;
    }
}
