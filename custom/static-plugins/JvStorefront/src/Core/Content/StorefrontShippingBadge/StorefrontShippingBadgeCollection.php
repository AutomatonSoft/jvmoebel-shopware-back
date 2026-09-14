<?php declare(strict_types=1);

namespace Jv\Storefront\Core\Content\StorefrontShippingBadge;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<StorefrontShippingBadgeEntity> */
final class StorefrontShippingBadgeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StorefrontShippingBadgeEntity::class;
    }
}
