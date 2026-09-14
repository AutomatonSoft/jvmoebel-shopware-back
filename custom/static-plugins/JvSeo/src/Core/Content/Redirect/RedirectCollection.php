<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\Redirect;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<RedirectEntity> */
final class RedirectCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return RedirectEntity::class;
    }
}
