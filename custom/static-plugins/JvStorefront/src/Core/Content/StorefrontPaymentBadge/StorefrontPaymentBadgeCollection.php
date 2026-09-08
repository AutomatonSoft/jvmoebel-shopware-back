<?php declare(strict_types=1);

namespace Jv\Storefront\Core\Content\StorefrontPaymentBadge;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<StorefrontPaymentBadgeEntity> */
final class StorefrontPaymentBadgeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StorefrontPaymentBadgeEntity::class;
    }
}
