<?php declare(strict_types=1);

namespace Jv\LeadManagement\Core\Content\Lead;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<LeadEntity> */
final class LeadCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LeadEntity::class;
    }
}