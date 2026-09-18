<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionTemplateEntity>
 */
final class OptionTemplateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionTemplateEntity::class;
    }
}
