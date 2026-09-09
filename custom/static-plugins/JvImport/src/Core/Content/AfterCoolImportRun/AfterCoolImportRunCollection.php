<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolImportRun;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<AfterCoolImportRunEntity> */
final class AfterCoolImportRunCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AfterCoolImportRunEntity::class;
    }
}
