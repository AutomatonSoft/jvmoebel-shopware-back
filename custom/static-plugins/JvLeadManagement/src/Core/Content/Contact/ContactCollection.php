<?php declare(strict_types=1);

namespace Jv\LeadManagement\Core\Content\Contact;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<ContactEntity> */
final class ContactCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ContactEntity::class;
    }
}
