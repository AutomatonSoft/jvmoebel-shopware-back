<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\OkbCategoryGroupAttribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<OkbCategoryGroupAttributeEntity> */
final class OkbCategoryGroupAttributeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OkbCategoryGroupAttributeEntity::class;
    }
}
