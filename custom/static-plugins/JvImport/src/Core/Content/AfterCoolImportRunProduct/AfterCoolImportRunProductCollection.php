<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolImportRunProduct;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<AfterCoolImportRunProductEntity> */
final class AfterCoolImportRunProductCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AfterCoolImportRunProductEntity::class;
    }
}
