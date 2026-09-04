<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\CatalogCategoryAttribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<CatalogCategoryAttributeEntity> */
final class CatalogCategoryAttributeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CatalogCategoryAttributeEntity::class;
    }
}
