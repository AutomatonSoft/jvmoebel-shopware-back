<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\RedirectSource;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<RedirectSourceEntity> */
final class RedirectSourceCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return RedirectSourceEntity::class;
    }
}
