<?php declare(strict_types=1);

namespace Jv\Storefront\Core\Content\StorefrontInternationalLink;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<StorefrontInternationalLinkEntity> */
final class StorefrontInternationalLinkCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StorefrontInternationalLinkEntity::class;
    }
}
