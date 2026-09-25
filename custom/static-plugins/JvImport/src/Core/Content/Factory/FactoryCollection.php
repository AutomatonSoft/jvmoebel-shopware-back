<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\Factory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<FactoryEntity> */
final class FactoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FactoryEntity::class;
    }
}
