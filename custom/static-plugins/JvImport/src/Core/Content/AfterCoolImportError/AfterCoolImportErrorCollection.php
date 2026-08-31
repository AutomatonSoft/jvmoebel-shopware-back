<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolImportError;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<AfterCoolImportErrorEntity> */
final class AfterCoolImportErrorCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AfterCoolImportErrorEntity::class;
    }
}
