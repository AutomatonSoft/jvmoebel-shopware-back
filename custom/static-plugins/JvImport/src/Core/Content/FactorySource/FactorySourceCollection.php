<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\FactorySource;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<FactorySourceEntity> */
final class FactorySourceCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FactorySourceEntity::class;
    }
}
