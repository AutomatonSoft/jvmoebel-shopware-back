<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Core\Content\CategoryContent;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<LegacyCategoryContentEntity> */
final class LegacyCategoryContentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LegacyCategoryContentEntity::class;
    }
}
