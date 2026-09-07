<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolProductSource;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<AfterCoolProductSourceEntity> */
final class AfterCoolProductSourceCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AfterCoolProductSourceEntity::class;
    }
}
