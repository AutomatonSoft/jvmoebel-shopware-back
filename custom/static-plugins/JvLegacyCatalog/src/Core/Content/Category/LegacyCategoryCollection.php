<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Core\Content\Category;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<LegacyCategoryEntity> */
final class LegacyCategoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LegacyCategoryEntity::class;
    }
}
