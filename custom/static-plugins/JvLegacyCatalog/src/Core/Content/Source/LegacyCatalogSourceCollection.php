<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Core\Content\Source;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<LegacyCatalogSourceEntity> */
final class LegacyCatalogSourceCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LegacyCatalogSourceEntity::class;
    }
}
