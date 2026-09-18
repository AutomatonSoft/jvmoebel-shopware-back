<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\RedirectChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<RedirectChannelEntity> */
final class RedirectChannelCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return RedirectChannelEntity::class;
    }
}
