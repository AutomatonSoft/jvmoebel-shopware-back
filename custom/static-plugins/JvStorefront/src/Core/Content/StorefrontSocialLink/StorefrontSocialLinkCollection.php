<?php declare(strict_types=1);

namespace Jv\Storefront\Core\Content\StorefrontSocialLink;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<StorefrontSocialLinkEntity> */
final class StorefrontSocialLinkCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StorefrontSocialLinkEntity::class;
    }
}
