<?php declare(strict_types=1);

namespace Jv\Storefront\Core\Content\StorefrontContactChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<StorefrontContactChannelEntity> */
final class StorefrontContactChannelCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StorefrontContactChannelEntity::class;
    }
}
