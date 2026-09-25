<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionTemplateValueEntity>
 */
final class OptionTemplateValueCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionTemplateValueEntity::class;
    }
}
